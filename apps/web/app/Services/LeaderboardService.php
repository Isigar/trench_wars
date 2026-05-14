<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MatchPlayerStat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Source: .planning/phases/09-polish/09-05-PLAN.md task 1 +
 *         09-RESEARCH.md § Pattern 3 + § Leaderboards SQL block.
 *
 * Top-N aggregator for SC-2 (/leaderboards). Wraps two grouped queries
 * (top players + top clans) in Cache::tags(...)->flexible([fresh,stale]).
 * Cache invalidation lives in MatchResultObserver + MatchPlayerStatObserver
 * + ClanMembershipObserver (task 2) — every domain mutation that can change
 * a leaderboard row flushes the `leaderboards` tag.
 *
 * D-09-05-C LOCKED — schema-vs-plan drift resolution:
 *   - The plan referenced `matches.game_id`; the actual schema routes the
 *     game scope through `matches.game_match_type_id → game_match_types.game_id`.
 *     This service filters via whereHas('gameMatchType', ...) for the player
 *     query, and via DB::table JOIN for the clan query.
 *   - The plan referenced `clan_memberships.active = true`; the actual schema
 *     uses `clan_memberships.left_at IS NULL` (partial unique index enforces
 *     D-009). The service filters by `left_at IS NULL`.
 *   - The plan referenced `clan_memberships.player_id`; the actual schema has
 *     `clan_memberships.user_id`. The clan JOIN must therefore route through
 *     `players.user_id`.
 *
 * D-09-05-D LOCKED — clan attribution snapshot semantics:
 *   The clan leaderboard JOINs on the *current* active membership, not the
 *   membership at the time-of-match. v1 schema does not snapshot membership
 *   into stat rows. If a player switches clans, their historical kills are
 *   re-attributed to the new clan on the next leaderboard refresh. This is
 *   acceptable for v1; a future ClanMembershipSnapshot would solve it.
 *
 * Pitfall 9 — Cache::flexible callback exception swallowing: the inner
 * callback wraps the compute in try/catch and calls `report($e)` before
 * rethrowing. The rethrow keeps `Cache::flexible`'s SWR semantics intact
 * (a failed refresh leaves the previous stale value in place) while the
 * report() surfaces the failure to Horizon's exception list so a
 * permanently-stale cache is detected.
 */
final class LeaderboardService
{
    /** @var array<string, int|null> */
    private const WINDOWS = [
        '7d' => 7,
        '30d' => 30,
        'all' => null,
    ];

    private const TTL_FRESH = 600;       // 10 minutes — fresh

    private const TTL_STALE = 3600;      // 1 hour — SWR window via Cache::flexible

    /**
     * Top N players ordered by total kills within the given window.
     *
     * D-09-05-E LOCKED — gameId is a UUID string (games.id is uuid). The plan
     * referenced `?int $gameId`; the actual schema's primary key for `games`
     * is `uuid`. Service accepts `?string $gameId` to match reality.
     *
     * @return Collection<int, object> Raw aggregate rows. Controllers (plan 09-06)
     *                                 wrap each row in LeaderboardEntryData::fromQueryResult().
     */
    public function topPlayers(string $window, ?string $gameId = null, int $limit = 25): Collection
    {
        $this->assertKnownWindow($window);
        $cappedLimit = $this->capLimit($limit);
        $cacheKey = "lb:players:{$window}:" . ($gameId ?? 'all') . ":{$cappedLimit}";

        return Cache::tags(['leaderboards', "lb:players:{$window}"])->flexible(
            key: $cacheKey,
            ttl: [self::TTL_FRESH, self::TTL_STALE],
            callback: fn (): Collection => $this->safeCompute(
                fn () => $this->computePlayerLeaderboard($window, $gameId, $cappedLimit),
            ),
        );
    }

    /**
     * Top N clans by total kills (attributed to current active clan member
     * via clan_memberships.left_at IS NULL) within the given window.
     *
     * @return Collection<int, object>
     */
    public function topClans(string $window, ?string $gameId = null, int $limit = 25): Collection
    {
        $this->assertKnownWindow($window);
        $cappedLimit = $this->capLimit($limit);
        $cacheKey = "lb:clans:{$window}:" . ($gameId ?? 'all') . ":{$cappedLimit}";

        return Cache::tags(['leaderboards', "lb:clans:{$window}"])->flexible(
            key: $cacheKey,
            ttl: [self::TTL_FRESH, self::TTL_STALE],
            callback: fn (): Collection => $this->safeCompute(
                fn () => $this->computeClanLeaderboard($window, $gameId, $cappedLimit),
            ),
        );
    }

    /**
     * Pitfall 9 mitigation — wrap any compute callback so a thrown exception
     * is reported to Horizon's exception list BEFORE rethrowing. The rethrow
     * is critical: Cache::flexible's SWR semantics only kick in when the
     * refresh callback throws; if we swallow we'd write a NULL into the
     * cache and never recover.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function safeCompute(\Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            report($e);

            throw $e;
        }
    }

    /**
     * @return Collection<int, object>
     */
    private function computePlayerLeaderboard(string $window, ?string $gameId, int $limit): Collection
    {
        $since = $this->sinceFor($window);

        /** @var Collection<int, object> $rows */
        $rows = MatchPlayerStat::query()
            ->selectRaw('player_id, SUM(kills) AS kills, SUM(deaths) AS deaths, COUNT(*) AS matches_played, (SUM(kills)::float / NULLIF(SUM(deaths), 0)) AS kdr')
            ->when($since, fn ($q) => $q->whereHas('match', fn ($q) => $q->where('scheduled_at', '>=', $since)))
            ->when(
                $gameId,
                fn ($q) => $q->whereHas('match.gameMatchType', fn ($q) => $q->where('game_id', $gameId)),
            )
            ->groupBy('player_id')
            ->orderByRaw('SUM(kills) DESC')
            ->limit($limit)
            ->get();

        return $rows;
    }

    /**
     * Per RESEARCH § Leaderboards SQL block (verbatim, with D-09-05-C
     * adaptations). DB::table chosen over Eloquent because every JOIN is
     * a primary key→foreign key path with no Eloquent relation that
     * simplifies it (the chain crosses match_player_stats → matches →
     * game_match_types AND match_player_stats → players → clan_memberships
     * AND matches → match_results, with two GROUP BY layers).
     *
     * Returns raw stdClass rows: { clan_id, kills, matches_played, wins }.
     *
     * @return Collection<int, object>
     */
    private function computeClanLeaderboard(string $window, ?string $gameId, int $limit): Collection
    {
        $since = $this->sinceFor($window);

        $query = DB::table('match_player_stats AS mps')
            ->join('matches AS m', 'm.id', '=', 'mps.match_id')
            ->join('players AS p', 'p.id', '=', 'mps.player_id')
            ->join('clan_memberships AS cm', function ($join): void {
                $join->on('cm.user_id', '=', 'p.user_id')
                    ->whereNull('cm.left_at');
            })
            ->leftJoin('match_results AS mr', 'mr.match_id', '=', 'm.id')
            ->selectRaw('cm.clan_id, SUM(mps.kills) AS kills, COUNT(DISTINCT mps.match_id) AS matches_played, SUM(CASE WHEN mr.winner_clan_id = cm.clan_id THEN 1 ELSE 0 END) AS wins')
            ->when($since, fn ($q) => $q->where('m.scheduled_at', '>=', $since))
            ->groupBy('cm.clan_id')
            ->orderByRaw('SUM(mps.kills) DESC')
            ->limit($limit);

        if ($gameId !== null) {
            $query->join('game_match_types AS gmt', 'gmt.id', '=', 'm.game_match_type_id')
                ->where('gmt.game_id', $gameId);
        }

        /** @var Collection<int, object> $rows */
        $rows = $query->get();

        return $rows;
    }

    private function sinceFor(string $window): ?Carbon
    {
        $days = self::WINDOWS[$window] ?? null;

        return $days === null ? null : Carbon::now()->subDays($days);
    }

    private function assertKnownWindow(string $window): void
    {
        if (! array_key_exists($window, self::WINDOWS)) {
            throw new InvalidArgumentException(
                "Unknown leaderboard window '{$window}'. Allowed: " . implode(', ', array_keys(self::WINDOWS)),
            );
        }
    }

    /**
     * T-09-05-03 (DoS) mitigation — service-layer ceiling of 100 rows even
     * if a caller passes a larger value. Plan 09-06's controller will also
     * cap at 100; this is the defence-in-depth duplicate.
     */
    private function capLimit(int $limit): int
    {
        return max(1, min($limit, 100));
    }
}
