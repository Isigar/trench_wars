<?php

declare(strict_types=1);

namespace App\Services\Rcon;

use App\Http\Controllers\Internal\MatchEventsController;
use App\Jobs\Rcon\CloseMatchJob;
use App\Models\GameMatch;
use App\Models\MatchEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Source: .planning/phases/08-rcon-automation/08-07-PLAN.md task 2 + <interfaces>
 *         MatchEventIngestService block.
 *
 * Idempotent batch ingest of canonical CRCON events. This is the seam where
 * worker → DB writes happen for the rcon-automation phase. Called by
 * {@see MatchEventsController::store} after
 * the HMAC middleware (plan 08-05) + FormRequest (plan 08-06) have already
 * authorised + array-shape-validated the incoming batch.
 *
 * Idempotency strategy (must_haves.truths #1 + #2):
 *   - Per-event {@see MatchEvent::create()} wrapped in a SAVEPOINT so a UNIQUE
 *     collision aborts only THAT event, not the whole batch. The composite
 *     UNIQUE `(match_id, crcon_stream_id)` on match_events absorbs replays —
 *     when the worker reconnects and resends a partially-acknowledged batch,
 *     the duplicates surface as `UniqueConstraintViolationException` which
 *     the service catches inside the SAVEPOINT and counts as `skipped`. One
 *     duplicate does NOT poison the other nine real events in the batch
 *     (T-08-07-02 disposition: accept).
 *   - **Postgres-specific:** Without the SAVEPOINT, a UNIQUE violation inside
 *     RefreshDatabase's wrapping transaction (or any outer transaction) aborts
 *     the whole transaction with SQLSTATE 25P02 — every subsequent statement
 *     in the batch fails with "current transaction is aborted". `DB::transaction()`
 *     nested inside an outer transaction issues a `SAVEPOINT` + `RELEASE` (or
 *     `ROLLBACK TO`) pair instead of a fresh `BEGIN`/`COMMIT`, scoping the
 *     abort to the per-event critical section. Each event therefore gets its
 *     own savepoint-bounded atomic try. (Plan 08-04 RESEARCH Pitfall 4 +
 *     Postgres docs § Subtransactions.)
 *
 * CloseMatchJob dispatch (must_haves.truths #4):
 *   - The service watches for `match_end` events during ingest. A single
 *     batch may legitimately contain multiple `match_end` rows if the worker
 *     witnesses round_end → match_end transitions across CRCON's
 *     `commander_match_end` action (plan 08-08's normaliser handles that),
 *     but only ONE CloseMatchJob is dispatched per ingest call — the job is
 *     idempotent on the upsert side anyway. Dispatch happens AFTER all events
 *     are persisted so the job's downstream aggregator (plan 08-08) sees the
 *     full event history.
 *
 * Failure semantics for non-UNIQUE exceptions (must_haves.truths #1 +
 * normaliser docblock + T-08-07-01):
 *   - Normaliser throws `InvalidArgumentException` → bubbles up to the
 *     controller → Laravel exception handler → 500 (operator alert). Events
 *     processed before the bad index ARE committed (per-event creates); the
 *     worker can safely resend the batch from the failed index because of
 *     UNIQUE absorb.
 *   - Other DB exceptions (connection drop mid-batch) also bubble — same
 *     partial-commit semantics. Worker retry path is the resilience layer.
 *
 * Why `final` + container-resolvable (D-04-09-D idiom — see plan must_haves.key_links):
 *   - `final` prevents subclass-based test doubles (Mockery's partial-mock
 *     anti-pattern). Tests construct the service directly with a fresh
 *     normaliser instance, OR rebind via the container for HTTP feature
 *     tests. Both paths exercise the real code.
 *   - Constructor-injects the normaliser (NOT a static method or service
 *     locator) so the container handles wiring + lifecycle.
 */
final class MatchEventIngestService
{
    public function __construct(
        private MatchEventNormaliser $normaliser,
    ) {}

    /**
     * Persist a batch of canonical events for a match. Idempotent via the
     * composite UNIQUE `(match_id, crcon_stream_id)` on match_events.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array{batch_id: string, accepted_count: int, skipped_count: int}
     *
     * @throws \InvalidArgumentException When any event payload misses the
     *                                   canonical shape for its event_type.
     */
    public function ingest(GameMatch $match, array $events): array
    {
        $batchId = (string) Str::uuid();
        $accepted = 0;
        $skipped = 0;
        $sawMatchEnd = false;

        foreach ($events as $raw) {
            // Normaliser throws InvalidArgumentException on bad shape; we let
            // it bubble. Partial commits before this index stay (idempotency
            // absorbs them on worker resend) — per T-08-07-02 disposition.
            $dto = $this->normaliser->validate($raw);

            try {
                // SAVEPOINT-scoped INSERT — Postgres aborts only this critical
                // section on UNIQUE violation, not the enclosing transaction.
                // See class docblock § Idempotency for the 25P02 rationale.
                DB::transaction(function () use ($match, $dto): void {
                    MatchEvent::create([
                        'match_id' => $match->id,
                        'event_type' => $dto->event_type,
                        'crcon_action' => $dto->crcon_action,
                        'crcon_stream_id' => $dto->crcon_stream_id,
                        'payload' => $dto->payload,
                        'occurred_at' => $dto->occurred_at,
                    ]);
                });
                $accepted++;

                if ($dto->event_type === 'match_end') {
                    $sawMatchEnd = true;
                }
            } catch (UniqueConstraintViolationException) {
                // Composite UNIQUE absorbs replays — silent no-op per truth #1.
                // SAVEPOINT was already ROLLBACK'd by DB::transaction's catch.
                $skipped++;
            }
        }

        if ($sawMatchEnd) {
            Bus::dispatch(new CloseMatchJob($match->id));
        }

        return [
            'batch_id' => $batchId,
            'accepted_count' => $accepted,
            'skipped_count' => $skipped,
        ];
    }
}
