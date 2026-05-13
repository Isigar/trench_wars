<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PublicTournamentData;
use App\Models\Tournament;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-12-PLAN.md <interfaces>
 *         TournamentShowController.
 *
 * Public GET /tournaments/{slug} — 5-tab Vue page (Overview / Bracket / Schedule
 * / Standings / Participants). No auth required (SC-3).
 *
 * Visibility:
 *   - is_public = false → abort(404) — non-disclosure idiom (mirrors
 *     MatchShowController T-04-10-02). Returning 403 would leak existence.
 *
 * Eager loading list (Phase 4 D-04-11-A N+1 rule):
 *   stages.brackets.participantA.clan
 *   stages.brackets.participantB.clan
 *   stages.brackets.winnerParticipant.clan
 *   stages.brackets.match
 *   standings.participant.clan
 *   participants.clan
 *   organiser
 *
 * Threat refs:
 *   - T-06-12-03 (info disclosure) — only PublicTournamentData (privacy-filtered DTO)
 *     surfaces on this controller; no admin-only fields.
 */
class TournamentShowController extends Controller
{
    public function __invoke(Tournament $tournament): Response
    {
        abort_unless($tournament->is_public, 404);

        $tournament->load([
            'stages.brackets.participantA.clan',
            'stages.brackets.participantB.clan',
            'stages.brackets.winnerParticipant.clan',
            'stages.brackets.match',
            'standings.participant.clan',
            'participants.clan',
            'organiser',
        ]);

        return Inertia::render('Tournaments/Show', [
            'tournament' => PublicTournamentData::fromModel($tournament),
        ]);
    }
}
