<?php

declare(strict_types=1);

namespace App\Jobs\Rcon;

use App\Services\Rcon\MatchEventIngestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Source: .planning/phases/08-rcon-automation/08-07-PLAN.md task 2 +
 *         <interfaces> CloseMatchJob block (Wave 5 placeholder).
 *
 * **Wave 5 placeholder — handle() is intentionally empty.** Plan 08-08 fills
 * the body with:
 *   1. Re-resolve GameMatch from `$matchId` (DB read; the model is NOT
 *      passed in to avoid the canonical "queue payload outlives the row"
 *      hazard — see SyncDiscordRolesJob docblock).
 *   2. Invoke MatchPlayerStatAggregator over the match_events stream to
 *      derive per-player stats + per-side scores.
 *   3. Insert or update the MatchResult row with `source='rcon'`.
 *   4. Honour the manual-override lock (ManualOverrideWinsTest, plan 08-08).
 *
 * The empty handle() is deliberate: plan 08-07's
 * {@see MatchEventIngestService} dispatches this job on
 * every `match_end` event, so Bus::fake() in MatchEventIngestServiceTest
 * needs a real class to resolve against. Plan 08-08 ships the behaviour;
 * this plan ships only the dispatch seam.
 *
 * Why `$matchId` is a string (UUID) and NOT a `GameMatch` instance:
 *   - Queue jobs serialise their constructor args to Redis. Carrying an
 *     Eloquent model means re-hydrating it on dequeue via the row that
 *     existed at dispatch time — if the row was deleted between dispatch
 *     and handle, SerializesModels throws ModelNotFoundException and the
 *     job dies in retry-spam mode.
 *   - Primitive IDs let handle() re-query at the moment of execution and
 *     gracefully exit on missing row (canonical Laravel queue idiom; see
 *     SyncDiscordRolesJob @ plan 05-06 for the same pattern).
 */
final class CloseMatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $matchId) {}

    /**
     * Plan 08-08 fills this with MatchResult upsert + manual-override gate.
     */
    public function handle(): void
    {
        // Intentionally empty — plan 08-07 ships only the dispatch seam.
    }
}
