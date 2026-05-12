<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PublicPlayerData;
use App\Models\Player;
use App\Models\User;
use App\Services\PlayerPrivacyGate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: .planning/phases/02-clans-tags/02-07-PLAN.md Task 1.
 *
 * Public GET /players/{player:slug} — player profile page.
 * Privacy gate runs FIRST; private→404 prevents existence disclosure (T-02-04-02).
 *
 * Security (T-02-04-02): abort(404) on tier failure — not 403 — to prevent
 * existence disclosure. Own-profile viewer always passes the tier check.
 */
class PlayerProfileController extends Controller
{
    public function __invoke(Player $player, Request $request, PlayerPrivacyGate $gate): Response
    {
        /** @var User|null $viewer */
        $viewer = $request->user();

        // T-02-04-02: Tier check MUST run before any DTO construction.
        // private → 404 (existence non-disclosure). community → 404 for guests.
        // clan → 404 when viewer not in same clan. public → pass.
        if (! $gate->passesTier($player, $viewer)) {
            abort(404);
        }

        $player->load(['privacy', 'user']);

        return Inertia::render('Players/Show', [
            'player' => PublicPlayerData::fromPlayer($player, $viewer, $gate),
        ]);
    }
}
