<?php

declare(strict_types=1);

namespace App\Http\Controllers\BotApi;

use App\Data\ClanData;
use App\Http\Controllers\Controller;
use App\Models\Clan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Source: 05-04-PLAN.md <interfaces> BotApiClanController block + 05-RESEARCH.md
 * SC-1 (clan read endpoints for slash-command embeds).
 *
 * Read-only endpoints under abilities:bot:read. The bot consumes these to render
 * /clan info, /clan list, and to resolve discord_role_id -> clan during
 * onboarding flows (Phase 5 plan 05-08+ slash commands).
 *
 * Threat refs: T-05-04-08 (information disclosure) — Clan rows are public by
 * design; no privacy filtering required.
 */
final class BotApiClanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 25)));

        $clans = Clan::query()
            ->with(['tags', 'activeMembers'])
            ->orderBy('name')
            ->paginate($limit);

        return response()->json([
            'data' => $clans->getCollection()
                ->map(fn (Clan $clan): ClanData => ClanData::fromModel($clan))
                ->all(),
            'meta' => [
                'current_page' => $clans->currentPage(),
                'per_page' => $clans->perPage(),
                'total' => $clans->total(),
                'last_page' => $clans->lastPage(),
            ],
        ]);
    }

    public function showByDiscordRole(string $discordRoleId): JsonResponse
    {
        $clan = Clan::query()
            ->with(['tags', 'activeMembers'])
            ->where('discord_role_id', $discordRoleId)
            ->firstOrFail();

        return response()->json([
            'data' => ClanData::fromModel($clan),
        ]);
    }
}
