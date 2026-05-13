<?php

declare(strict_types=1);

namespace App\Http\Controllers\BotApi;

use App\Data\PublicMatchData;
use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Source: 05-04-PLAN.md <interfaces> BotApiMatchController block + 05-RESEARCH.md
 * SC-1 (match read endpoints for `/matches` slash-command list + `/match info`).
 *
 * Read-only endpoints under abilities:bot:read. Route binding {match} resolves
 * to App\Models\GameMatch via Laravel's implicit binding — D-04-03-A LOCKED:
 * the class name is GameMatch (NOT Match — PHP 8 reserved keyword).
 *
 * Threat refs:
 *   T-05-04-07 (DoS via unbounded list) — index() clamps `limit` query param
 *     to a max of 50 rows per page.
 *
 * `is_public=false` matches are excluded from the bot listing — same shape as
 * the Phase 4 web calendar (MatchCalendarController). Bot does NOT have a back
 * door into private matches.
 */
final class BotApiMatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->query('limit', 25)));
        $status = (string) $request->query('status', 'open');

        $matches = GameMatch::query()
            ->where('status', $status)
            ->where('is_public', true)
            ->with(['gameMatchType', 'hostClan', 'slots.role', 'slots.occupantUser.player'])
            ->orderBy('scheduled_at')
            ->paginate($limit);

        return response()->json([
            'data' => $matches->getCollection()
                ->map(fn (GameMatch $match): PublicMatchData => PublicMatchData::fromModel($match))
                ->all(),
            'meta' => [
                'current_page' => $matches->currentPage(),
                'per_page' => $matches->perPage(),
                'total' => $matches->total(),
                'last_page' => $matches->lastPage(),
            ],
        ]);
    }

    public function show(GameMatch $match): JsonResponse
    {
        $match->load(['gameMatchType', 'hostClan', 'slots.role', 'slots.occupantUser.player']);

        return response()->json([
            'data' => PublicMatchData::fromModel($match),
        ]);
    }
}
