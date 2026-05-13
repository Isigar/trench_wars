<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameMatch;
use App\Models\MatchResult;
use App\Models\User;
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
}
