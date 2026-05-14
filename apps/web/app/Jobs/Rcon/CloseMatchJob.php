<?php

declare(strict_types=1);

namespace App\Jobs\Rcon;

use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Services\MatchResultService;
use App\Services\Rcon\MatchEventIngestService;
use App\Services\Rcon\MatchPlayerStatAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Source: .planning/phases/08-rcon-automation/08-07-PLAN.md task 2 +
 *         .planning/phases/08-rcon-automation/08-08-PLAN.md task 2 +
 *         <interfaces> CloseMatchJob block.
 *
 * Plan 08-07 shipped this as a Wave-5 placeholder (empty handle()) so
 * MatchEventIngestService could dispatch against a real class via Bus::fake().
 * Plan 08-08 fills the body:
 *
 *   1. Re-resolve {@see GameMatch} from `$matchId` (primitive ID; see
 *      "Why $matchId is a string" docblock below). `findOrFail` — a
 *      missing-row is a hard failure (deleted between dispatch and handle).
 *   2. Run {@see MatchPlayerStatAggregator::aggregate()} to roll up
 *      `match_events` into `match_player_stats` (per-player counters).
 *   3. Look up the latest `match_end` event for this match. If absent
 *      (failure path: worker streamed disconnect events but no MATCH ENDED
 *      ever fired), flip `manual_entry_required=true` on the match and
 *      RETURN — do NOT write a MatchResult row (low-confidence path per
 *      08-RESEARCH.md failure-table).
 *   4. Count `player_kill` events. If zero (failure path: match_end fired
 *      but the worker captured zero kill rows), flip
 *      `manual_entry_required=true` (low-confidence flag) AND still write
 *      the MatchResult row (best-effort — admin will curate).
 *   5. Build `$resultData` from the match_end payload (allies_score,
 *      axis_score, recorded_at). `winner_clan_id` stays null — round-1
 *      cannot map CRCON's `allies`/`axis` team labels to clan IDs
 *      deterministically; admin curates after.
 *   6. Call {@see MatchResultService::upsertFromRcon()}, which honours the
 *      manual-override invariant + flips match.status -> 'played'.
 *
 * **Why `$matchId` is a string (UUID) and NOT a `GameMatch` instance:**
 *   - Queue jobs serialise their constructor args to Redis. Carrying an
 *     Eloquent model means re-hydrating it on dequeue via the row that
 *     existed at dispatch time — if the row was deleted between dispatch
 *     and handle, SerializesModels throws ModelNotFoundException and the
 *     job dies in retry-spam mode.
 *   - Primitive IDs let handle() re-query at the moment of execution and
 *     gracefully surface a missing row via `findOrFail` (same Laravel
 *     idiom as Phase 5's SyncDiscordRolesJob).
 *
 * Dispatched by:
 *   - {@see MatchEventIngestService::ingest()} on every batch that
 *     contains at least one `match_end` event.
 */
final class CloseMatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $matchId) {}

    /**
     * Aggregate + upsert + status flip — see class docblock for the 6-step
     * algorithm and failure paths.
     *
     * Service collaborators are method-injected via Laravel's container so
     * the job stays test-friendly (no Mockery; the services are `final` per
     * D-04-09-D and exercise real code in feature tests).
     */
    public function handle(
        MatchPlayerStatAggregator $aggregator,
        MatchResultService $resultService,
    ): void {
        $match = GameMatch::findOrFail($this->matchId);

        // 1. Aggregate per-player stats from match_events.
        $aggregator->aggregate($match);

        // 2. Derive the result row from the latest match_end event.
        /** @var MatchEvent|null $endEvent */
        $endEvent = MatchEvent::where('match_id', $match->id)
            ->where('event_type', 'match_end')
            ->latest('occurred_at')
            ->first();

        if ($endEvent === null) {
            // FAILURE PATH: no match_end captured. Flag for manual entry
            // per 08-RESEARCH.md failure-table. No MatchResult written —
            // admin enters the result via the Filament UI.
            $match->update(['manual_entry_required' => true]);

            return;
        }

        // 3. Low-confidence guard: zero kill events means CRCON likely
        //    dropped the log stream mid-match. Flip the manual-entry flag
        //    BUT still write the MatchResult (best-effort; admin will
        //    curate from the partial data).
        $killCount = MatchEvent::where('match_id', $match->id)
            ->where('event_type', 'player_kill')
            ->count();
        if ($killCount === 0) {
            $match->update(['manual_entry_required' => true]);
        }

        // 4. Build result data from match_end payload.
        /** @var array<string, mixed> $payload */
        $payload = $endEvent->payload;

        $alliesScore = isset($payload['allies_score']) && is_int($payload['allies_score'])
            ? $payload['allies_score']
            : null;
        $axisScore = isset($payload['axis_score']) && is_int($payload['axis_score'])
            ? $payload['axis_score']
            : null;

        $resultData = [
            'allies_score' => $alliesScore,
            'axis_score' => $axisScore,
            // Round-1: cannot map team -> clan deterministically. Admin
            // curates winner_clan_id after the auto-write lands.
            'winner_clan_id' => null,
            'recorded_at' => $endEvent->occurred_at,
        ];

        // 5. Upsert (honours manual-override invariant + flips status).
        $resultService->upsertFromRcon($match, $resultData);
    }
}
