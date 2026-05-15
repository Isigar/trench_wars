<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DisputeAlreadyOpenException;
use App\Exceptions\InvalidDisputeTransitionException;
use App\Models\GameMatch;
use App\Models\MatchDispute;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Source: .planning/phases/09-polish/09-07-PLAN.md task 1 +
 *         09-RESEARCH.md § Moderator Tooling — Match Disputes +
 *         09-02 migration `one_open_dispute_per_user_per_match` partial UNIQUE
 *         (Pitfall 11 — Postgres-only partial index).
 *
 * DisputeService — the canonical mutation surface for `match_disputes`.
 *
 * State machine (plan 09-07 LOCKED — supersedes the older docblock comment in
 * the migration which mentioned `dismissed | withdrawn`; the migration stores a
 * free string so the service owns the allow-list):
 *
 *   open          -> under_review
 *   under_review  -> resolved | rejected
 *   rejected      -> under_review   (re-open after rejection)
 *
 * `resolved` is terminal *unless* a moderator explicitly amends it (no
 * transition out of resolved in v1 — admin must hand-edit the row, audit
 * trail captures it).
 *
 * Resolution enum (only set when status transitions to 'resolved'):
 *   result_amended | result_voided | no_action | sanction_issued
 *
 * D-09-03-A LOCKED (Ban.php / MatchDispute.php docblock): NO LogsActivity
 * trait on MatchDispute. The trait would emit `MatchDispute created/updated`
 * skeleton lines with no human-readable subject — this service writes the
 * canonical row explicitly so the audit timeline reads "Alice opened a
 * dispute on match X" / "Bob moved dispute Y from open to under_review".
 *
 * NAMING (D-04-03-A LOCKED): owner model is `App\Models\GameMatch`. The
 * BelongsTo on MatchDispute is explicitly keyed by `match_id`.
 *
 * Threat refs:
 *   - T-09-07-06 (I) — body access gated at MatchDisputeResource (canViewAny);
 *     service does not enforce, controllers do.
 *   - T-09-07-07 (T) — state machine validated here, MatchDisputeWorkflowTest
 *     locks every illegal pair.
 *
 * Stateless — auto-resolved by the Laravel container.
 */
final class DisputeService
{
    /**
     * Allowed transitions. An empty array means the state is terminal (no outgoing).
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'open' => ['under_review'],
        'under_review' => ['resolved', 'rejected'],
        'rejected' => ['under_review'],
        'resolved' => [],
    ];

    /**
     * Valid resolution enum values. Only meaningful when transitioning to 'resolved'.
     *
     * @var list<string>
     */
    public const RESOLUTIONS = [
        'result_amended',
        'result_voided',
        'no_action',
        'sanction_issued',
    ];

    /** Terminal statuses — set `resolved_at` + `resolved_by_user_id` when entered. */
    private const TERMINAL_STATUSES = ['resolved', 'rejected'];

    /**
     * Open a new dispute on $match by $raisedBy.
     *
     * Enforces the partial unique index (one open dispute per match per user)
     * by catching QueryException 23505 and re-throwing as a domain exception.
     * Pattern mirrors Phase 4 MatchSignupService (D-04-06-A) — never preflight
     * via SELECT-then-INSERT (TOCTOU race); let the DB be the arbiter.
     *
     * @throws DisputeAlreadyOpenException When the user already has an open
     *                                     dispute on this match.
     */
    public function open(GameMatch $match, User $raisedBy, string $body): MatchDispute
    {
        try {
            /** @var MatchDispute $dispute */
            $dispute = DB::transaction(function () use ($match, $raisedBy, $body): MatchDispute {
                /** @var MatchDispute $created */
                $created = MatchDispute::query()->create([
                    'match_id' => $match->id,
                    'raised_by_user_id' => $raisedBy->id,
                    'body' => $body,
                    'status' => 'open',
                ]);

                activity()
                    ->causedBy($raisedBy)
                    ->performedOn($match)
                    ->withProperties([
                        'dispute_id' => $created->id,
                        'body' => $body,
                    ])
                    ->log('match.dispute_opened');

                return $created;
            });

            return $dispute;
        } catch (QueryException $e) {
            // Postgres SQLSTATE 23505 = unique_violation; partial UNIQUE
            // `one_open_dispute_per_user_per_match` is the only one this
            // table can violate via INSERT.
            if ($e->getCode() === '23505') {
                throw new DisputeAlreadyOpenException(
                    matchId: (string) $match->id,
                    raisedByUserId: (string) $raisedBy->id,
                );
            }

            throw $e;
        }
    }

    /**
     * Transition $dispute to $toStatus, attributing the action to $by.
     *
     * @param  string|null  $resolution  Required when $toStatus='resolved';
     *                                   one of self::RESOLUTIONS. Ignored otherwise.
     * @param  string|null  $notes  Free-text moderator narrative; persisted
     *                              as resolution_notes (column name preserved
     *                              from the migration even for non-resolved
     *                              transitions to keep the audit trail
     *                              single-column).
     *
     * @throws InvalidDisputeTransitionException When (from, to) is not in ALLOWED_TRANSITIONS.
     * @throws InvalidArgumentException When transitioning to 'resolved' without a valid resolution.
     */
    public function transition(
        MatchDispute $dispute,
        string $toStatus,
        ?string $resolution,
        ?string $notes,
        User $by,
    ): MatchDispute {
        $from = $dispute->status;
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

        if (! in_array($toStatus, $allowed, true)) {
            throw new InvalidDisputeTransitionException($from, $toStatus);
        }

        if ($toStatus === 'resolved') {
            if ($resolution === null || ! in_array($resolution, self::RESOLUTIONS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Transition to resolved requires a valid resolution (one of: %s).',
                    implode(', ', self::RESOLUTIONS),
                ));
            }
        } else {
            // Drop any caller-supplied resolution on non-resolved transitions —
            // the column is only meaningful for status='resolved'. Keeping it
            // null on other states means a downstream query like
            // `WHERE resolution IS NOT NULL` correctly counts only resolved rows.
            $resolution = null;
        }

        $isTerminal = in_array($toStatus, self::TERMINAL_STATUSES, true);

        DB::transaction(function () use ($dispute, $from, $toStatus, $resolution, $notes, $by, $isTerminal): void {
            $updates = [
                'status' => $toStatus,
                'resolution' => $resolution,
                'resolution_notes' => $notes,
            ];

            if ($isTerminal) {
                $updates['resolved_by_user_id'] = $by->id;
                $updates['resolved_at'] = now();
            } else {
                // Re-opening (rejected -> under_review) clears the terminal columns
                // so that a fresh terminal transition can write them again. Without
                // this, an "amended ruling" loses its causer because resolved_by
                // would still point at the original moderator.
                $updates['resolved_by_user_id'] = null;
                $updates['resolved_at'] = null;
            }

            $dispute->update($updates);

            // Subject of the activity row is the OWNING GameMatch (UUID PK), NOT
            // the MatchDispute bigint PK — activity_log.subject_id was migrated
            // to `uuid` in plan 01-14 (HasUuids domain models), so MatchDispute's
            // bigint id cannot be coerced into the column. The dispute_id lives
            // in properties for downstream cross-reference. This also matches
            // the open-dispute row (subject=match) and produces a coherent
            // per-match dispute audit timeline when filtering by subject.
            // D-09-07-A — Rule 1 fix; documented in SUMMARY.
            $dispute->loadMissing('match');
            $matchSubject = $dispute->match ?? throw new \RuntimeException(
                sprintf('MatchDispute %d has no associated match (FK integrity failure).', (int) $dispute->id),
            );

            activity()
                ->causedBy($by)
                ->performedOn($matchSubject)
                ->withProperties([
                    'dispute_id' => $dispute->id,
                    'from' => $from,
                    'to' => $toStatus,
                    'resolution' => $resolution,
                    'notes' => $notes,
                ])
                ->log('match.dispute_transitioned');
        });

        return $dispute->fresh() ?? $dispute;
    }

    /**
     * Convenience: list legal next states from the current status.
     *
     * Filament Action form Select populates options from this so the UI never
     * offers an illegal transition — defence in depth (the service still
     * validates).
     *
     * @return list<string>
     */
    public function nextStatesFor(MatchDispute $dispute): array
    {
        return self::ALLOWED_TRANSITIONS[$dispute->status] ?? [];
    }
}
