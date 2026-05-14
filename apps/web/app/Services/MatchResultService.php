<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\Rcon\CloseMatchJob;
use App\Models\GameMatch;
use App\Models\MatchResult;
use App\Models\User;
use Database\Seeders\RconWorkerSystemUserSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Source: 04-09-PLAN.md Task 1 + RESEARCH Pattern 4 (status state machine) +
 *         <interfaces> MatchResultService snippet.
 *
 * Canonical write path for match results. Used by:
 *   - Filament ResultRelationManager (admin entry/override path; SC-4)
 *   - Phase 4+ controllers / Discord bot flows when result entry becomes self-service
 *
 * Atomic semantics (T-04-09-04 mitigation): the result write and the status flip to
 * 'played' happen inside a SINGLE DB::transaction — a partial state where the
 * MatchResult row lands but the GameMatch.status stays 'open' is impossible.
 *
 * Idempotency: `updateOrCreate` keys on `match_id` (UNIQUE at DB layer — plan 04-02);
 * a second call on the same match overwrites the existing result row without rotating
 * status (which has already moved to 'played' on the first call). The terminal-status
 * skip below prevents MatchStatusService from rejecting 'played -> played' (terminal
 * states have no outgoing transitions per Pattern 4).
 *
 * NAMING NOTE (D-04-03-A LOCKED + D-04-08-A continuation): the Match model is named
 * `App\Models\GameMatch`. This service uses `GameMatch` directly — no
 * `App\Models\Match as MatchModel` alias is needed (no `match($x)` PHP 8 expressions
 * appear in the file).
 *
 * Stateless — auto-resolved by the Laravel container.
 */
final class MatchResultService
{
    /**
     * Sentinel email for the RCON worker service user, seeded by
     * {@see RconWorkerSystemUserSeeder}. Resolved at
     * upsertFromRcon-time via firstOrFail — a missing user means the seeder
     * never ran (CI tests + production both run seeders on boot per
     * .planning/STATE.md plan-01 seeders contract).
     */
    private const RCON_WORKER_EMAIL = 'rcon-worker@system.trenchwars';

    /**
     * Upsert the result row for a match, then atomically transition status to 'played'.
     *
     * The status flip skips when the match is already 'played' (re-edits to an existing
     * result do not re-fire the transition — Pattern 4 terminal-state rule).
     *
     * Pre-conditions enforced by MatchStatusService::transition() on the first call:
     *   - current status must be 'open' or 'locked' (the only states with 'played' in
     *     ALLOWED_TRANSITIONS). A 'draft' match throws DomainException because draft
     *     must pass through 'open' first.
     *
     * Audit trail (T-04-09-07): both the MatchResult write (LogsActivity on the model)
     * AND the MatchStatusService transition (activity() log row with from/to + causer)
     * are captured. Two activity rows land per first-time result entry.
     *
     * @param  array<string, mixed>  $data
     */
    public function upsert(GameMatch $match, array $data, User $causer): MatchResult
    {
        return DB::transaction(function () use ($match, $data, $causer): MatchResult {
            /** @var MatchResult $result */
            $result = MatchResult::updateOrCreate(
                ['match_id' => $match->id],
                [
                    'winner_clan_id' => $data['winner_clan_id'] ?? null,
                    'allies_score' => $data['allies_score'] ?? null,
                    'axis_score' => $data['axis_score'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'recorded_by_user_id' => $causer->id,
                    'recorded_at' => $data['recorded_at'] ?? now(),
                ],
            );

            // Atomic side-effect: flip status to 'played' on first result write.
            // Skip when already terminal — Pattern 4 disallows 'played -> played'.
            if ($match->status !== 'played') {
                app(MatchStatusService::class)->transition($match, 'played', $causer);
            }

            return $result;
        });
    }

    /**
     * Auto-populate the result row from an RCON match_end event payload.
     *
     * Called by {@see CloseMatchJob::handle()} after the
     * MatchPlayerStatAggregator has rolled up player stats.
     *
     * **Manual-override invariant (T-08-08-01 mitigation — must_haves.truths #3):**
     *   - If a MatchResult row already exists with `source='manual'`, this
     *     method DOES NOT overwrite it. Instead it:
     *       1. Writes an activity_log entry with `properties.event =
     *          'rcon.arrived_but_manual_locked'` + `properties.would_have_set`
     *          (the scores/winner the RCON event would have set).
     *       2. Returns the existing manual row unchanged.
     *   - This is the integrity boundary for the RCON-vs-admin trust
     *     conflict (D-019 — operator override is the source of truth).
     *
     * **Why audit-log written via `activity()->withProperties()->log()` (D-04-12-A):**
     *   - The model's LogsActivity trait emits create/update/delete events
     *     only; a "no-op skip" doesn't fire any of those events. To capture
     *     the would-have-been-set values for forensic review, we write the
     *     audit row imperatively. The `withProperties` payload is the only
     *     path that populates Activity::$properties in this codebase.
     *
     * **Causer attribution (must_haves.truths #5):**
     *   - The recorded_by_user_id is the seeded SYSTEM_RCON_WORKER user
     *     (resolved by sentinel email). T-08-08-03 disposition: accept —
     *     the user has no roles/permissions and is_admin is not a column;
     *     attribution-only.
     *
     * **Status flip (must_haves.truths #1):**
     *   - On a successful upsert, transitions the match to 'played' via
     *     MatchStatusService::transition. Skipped when already terminal.
     *
     * **Atomicity (D-04-09-04 mitigation, inherited from upsert()):**
     *   - The audit-log lookup, would-have-set audit (when locked), result
     *     write, and status flip all live inside a single DB::transaction
     *     so a partial state is impossible. Re-entrant safe — CloseMatchJob
     *     retries land the same end state.
     *
     * Source-column behaviour:
     *   - On insert: source='rcon' (explicit fillable write — overrides the
     *     DB DEFAULT 'manual' from migration 08-02 task 2).
     *   - On update of an existing source='rcon' row (re-run): source stays
     *     'rcon' (explicit write again).
     *   - On encountering an existing source='manual' row: NO write happens
     *     (early return). The row remains source='manual' unchanged.
     *
     * @param  array<string, mixed>  $data  Payload-derived data:
     *                                      winner_clan_id?, allies_score?,
     *                                      axis_score?, notes?, recorded_at?
     */
    public function upsertFromRcon(GameMatch $match, array $data): MatchResult
    {
        return DB::transaction(function () use ($match, $data): MatchResult {
            /** @var MatchResult|null $existing */
            $existing = MatchResult::where('match_id', $match->id)->first();

            // INVARIANT: Manual override always wins (D-019).
            if ($existing !== null && $existing->source === 'manual') {
                activity()
                    ->performedOn($existing)
                    ->withProperties([
                        'event' => 'rcon.arrived_but_manual_locked',
                        'would_have_set' => [
                            'winner_clan_id' => $data['winner_clan_id'] ?? null,
                            'allies_score' => $data['allies_score'] ?? null,
                            'axis_score' => $data['axis_score'] ?? null,
                        ],
                    ])
                    ->log(__('rcon.audit.rcon_arrived_locked'));

                return $existing;
            }

            /** @var User $rconUser */
            $rconUser = User::where('email', self::RCON_WORKER_EMAIL)->firstOrFail();

            /** @var MatchResult $result */
            $result = MatchResult::updateOrCreate(
                ['match_id' => $match->id],
                [
                    'winner_clan_id' => $data['winner_clan_id'] ?? null,
                    'allies_score' => $data['allies_score'] ?? null,
                    'axis_score' => $data['axis_score'] ?? null,
                    'notes' => $data['notes'] ?? __('rcon.audit.automated_from_crcon'),
                    'recorded_by_user_id' => $rconUser->id,
                    'recorded_at' => $data['recorded_at'] ?? now(),
                    'source' => 'rcon',
                ],
            );

            // Atomic side-effect: flip status to 'played' on first result write.
            // Skip when already terminal — Pattern 4 disallows 'played -> played'.
            if ($match->status !== 'played') {
                app(MatchStatusService::class)->transition($match, 'played', $rconUser);
            }

            return $result;
        });
    }
}
