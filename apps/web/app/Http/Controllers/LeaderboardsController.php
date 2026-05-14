<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\LeaderboardClanEntryData;
use App\Data\LeaderboardEntryData;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\Game;
use App\Models\Player;
use App\Services\LeaderboardService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: .planning/phases/09-polish/09-06-PLAN.md task 2.
 *
 * Public Inertia controller for /leaderboards (SC-2).
 *
 * Aggregates from `LeaderboardService::topPlayers()` + `topClans()` and
 * hydrates each row through the privacy-gated DTO factories:
 *   - LeaderboardEntryData::fromQueryResult() runs PlayerPrivacyGate so
 *     players with `show_stats=false` (or whose tier excludes the viewer)
 *     render anonymously (D-018, T-09-06-01 mitigation).
 *   - LeaderboardClanEntryData::fromQueryResult() — clans are always public.
 *
 * Query params:
 *   - window: 7d|30d|all   (default '7d')
 *   - game:   uuid|null    (default null = all games)
 *   - limit:  1..100       (default 25; service caps at 100, controller too —
 *                           defence-in-depth per T-09-05-03 / T-09-06-05)
 */
class LeaderboardsController extends Controller
{
    /** @var array<int, string> */
    private const ALLOWED_WINDOWS = ['7d', '30d', 'all'];

    private const DEFAULT_LIMIT = 25;

    private const MAX_LIMIT = 100;

    public function index(Request $request, LeaderboardService $service): Response
    {
        $validated = $request->validate([
            'window' => ['nullable', 'string', 'in:7d,30d,all'],
            'game' => ['nullable', 'string', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
        ]);

        $window = $validated['window'] ?? '7d';
        $gameId = $validated['game'] ?? null;
        $limit = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);

        // Defence-in-depth: cap again at the controller boundary (the service
        // also caps to 100; T-09-06-05 mitigation).
        $limit = max(1, min($limit, self::MAX_LIMIT));

        $viewer = $request->user();

        $playerRows = $service->topPlayers($window, $gameId, $limit);
        $clanRows = $service->topClans($window, $gameId, $limit);

        // ─── Eager-load Players + Privacy + active clan membership for the
        // player rows (Pattern 6 — no lazy fetch inside DTO factory).
        /** @var array<int, string> $playerIds */
        $playerIds = $playerRows->map(fn (object $r) => (string) ($r->player_id ?? ''))
            ->filter(fn (string $id) => $id !== '')
            ->values()
            ->all();

        /** @var Collection<int, Player> $players */
        $players = Player::query()
            ->with('privacy')
            ->whereIn('id', $playerIds)
            ->get()
            ->keyBy('id');

        // Resolve each Player's current active clan_id → clan_name in one pass.
        /** @var array<int, string> $userIds */
        $userIds = $players->pluck('user_id')->filter()->unique()->values()->all();

        /** @var array<string, string> $userIdToClanName */
        $userIdToClanName = [];
        if ($userIds !== []) {
            $memberships = ClanMembership::query()
                ->with('clan:id,name')
                ->whereIn('user_id', $userIds)
                ->whereNull('left_at')
                ->get();

            foreach ($memberships as $membership) {
                $clan = $membership->clan;
                if ($clan !== null) {
                    $userIdToClanName[(string) $membership->user_id] = (string) $clan->name;
                }
            }
        }

        /** @var array<int, LeaderboardEntryData> $playerEntries */
        $playerEntries = [];
        foreach ($playerRows as $row) {
            $playerId = (string) ($row->player_id ?? '');
            $player = $players->get($playerId);
            if ($player === null) {
                // The player row was deleted between the aggregate query and
                // hydration; skip rather than crash. The leaderboard cache
                // will re-converge on the next flush.
                continue;
            }

            $clanName = $userIdToClanName[(string) $player->user_id] ?? null;

            $playerEntries[] = LeaderboardEntryData::fromQueryResult($row, $player, $viewer, $clanName);
        }

        // ─── Eager-load Clans for the clan rows (Pattern 6 — no lazy fetch).
        /** @var array<int, string> $clanIds */
        $clanIds = $clanRows->map(fn (object $r) => (string) ($r->clan_id ?? ''))
            ->filter(fn (string $id) => $id !== '')
            ->values()
            ->all();

        /** @var Collection<int, Clan> $clans */
        $clans = Clan::query()
            ->whereIn('id', $clanIds)
            ->get()
            ->keyBy('id');

        /** @var array<int, LeaderboardClanEntryData> $clanEntries */
        $clanEntries = [];
        foreach ($clanRows as $row) {
            $clanId = (string) ($row->clan_id ?? '');
            $clan = $clans->get($clanId);
            if ($clan === null) {
                continue;
            }
            $clanEntries[] = LeaderboardClanEntryData::fromQueryResult($row, $clan);
        }

        // Game filter dropdown options (id + name + key for the slug-style scoping).
        $games = Game::query()
            ->orderByRaw("COALESCE((name->>'en')::text, key)")
            ->get(['id', 'key', 'name']);

        return Inertia::render('Leaderboards/Index', [
            'players' => $playerEntries,
            'clans' => $clanEntries,
            'filters' => [
                'window' => $window,
                'game' => $gameId,
                'limit' => $limit,
            ],
            'games' => $games,
            'allowed_windows' => self::ALLOWED_WINDOWS,
        ]);
    }
}
