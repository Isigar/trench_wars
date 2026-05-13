<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Tournament;

/**
 * Forward-declared observer stub — registered by Tournament::booted() so the
 * model can call `static::observe(TournamentObserver::class)` from day one
 * without a class-existence fatal at app boot.
 *
 * Plan 06-10 replaces the empty method bodies with the real implementations:
 *   - saved()   : Event::updateOrCreate / delete (polymorphic calendar sync)
 *   - created() : DiscordOutboundMessage `tournament_announce` writer
 *   - updated() : wasChanged('status') -> DiscordOutboundMessage `tournament_status_update`
 *
 * Threat ref T-06-03-03: registering an observer whose class doesn't yet exist
 * crashes the application at boot. Shipping this stub now is the canonical
 * Phase-by-phase forward-declaration pattern (Phase 4 plan 04-08 mirrors this
 * with MatchObserver bodies landing in their own dedicated plan).
 *
 * Source: .planning/phases/06-tournaments-brackets/06-03-PLAN.md task 1 +
 *         .planning/phases/06-tournaments-brackets/06-10-PLAN.md (real bodies).
 */
class TournamentObserver
{
    public function saved(Tournament $tournament): void
    {
        // Intentionally empty — plan 06-10 fills this with Event row sync.
    }

    public function created(Tournament $tournament): void
    {
        // Intentionally empty — plan 06-10 fills this with DiscordOutboundMessage
        // `tournament_announce` writer (gated by is_public + organiser channel cfg).
    }

    public function updated(Tournament $tournament): void
    {
        // Intentionally empty — plan 06-10 fills this with
        // wasChanged('status') -> DiscordOutboundMessage `tournament_status_update`.
    }
}
