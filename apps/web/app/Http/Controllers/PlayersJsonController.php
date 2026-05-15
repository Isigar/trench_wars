<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Source: .planning/phases/09-polish/09-11-PLAN.md task 1 must_haves —
 * "/players.json carries throttle:public-api" + PublicApiThrottleTest fixture.
 *
 * Public GET /players.json — slim JSON list of players for external
 * consumers (Discord bot enrichment, scraper-friendly index, future
 * static-site exports). Mirrors ClansJsonController shape so any caller
 * can hit both endpoints with the same parsing logic.
 *
 * Threat refs:
 *   - T-09-11-01 (D — mass scraping) — mitigated by throttle:public-api
 *     (30/min by IP) attached at the route layer.
 *
 * Privacy:
 *   v1 returns slug + display_name + country_code only — these are the
 *   public attributes always shown on /players/{slug}. PlayerPrivacy tier
 *   filtering is intentionally NOT applied here because the slug + display
 *   name are public-by-definition (the slug is the canonical URL). Future
 *   plan can extend this with stats columns gated on PlayerPrivacyGate if
 *   the public surface needs richer payloads.
 */
final class PlayersJsonController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $players = Player::query()
            ->orderBy('display_name')
            ->limit(500)
            ->get(['id', 'slug', 'display_name', 'country_code']);

        return response()->json([
            'data' => $players->map(static fn (Player $player): array => [
                'id' => $player->id,
                'slug' => $player->slug,
                'display_name' => $player->display_name,
                'country_code' => $player->country_code,
            ])->all(),
            'count' => $players->count(),
        ]);
    }
}
