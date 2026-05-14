<?php

declare(strict_types=1);

namespace App\Services\Rcon;

use App\Jobs\Rcon\CloseMatchJob;
use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Models\MatchPlayerStat;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

/**
 * Source: .planning/phases/08-rcon-automation/08-08-PLAN.md task 1 +
 *         <interfaces> MatchPlayerStatAggregator block.
 *
 * Rolls up the append-only `match_events` stream for a single match into
 * per-player aggregated counters in `match_player_stats`. Invoked by
 * {@see CloseMatchJob} on a `match_end` event arrival.
 *
 * **Run-once-on-match_end (Pitfall 4 mitigation — must_haves.truths #2):**
 *   - This aggregator is NOT called per-event during the match. CloseMatchJob
 *     calls it exactly once after the worker streams the final `match_end`
 *     event. Per-event re-aggregation would (a) inflate cost (N² scans of the
 *     event stream), and (b) introduce a race window where partial mid-match
 *     stats could leak into Filament admin views or the bot embed.
 *
 * **Idempotency (must_haves.truths #4):**
 *   - The `updateOrCreate` upsert is keyed on the composite UNIQUE
 *     `(match_id, player_id)` enforced by the 08-02 migration. Re-running the
 *     aggregator on the same match yields IDENTICAL match_player_stats rows
 *     (kill counts overwrite the existing row's counts, not increment). This
 *     makes CloseMatchJob safe to retry (Horizon will retry on transient
 *     failures; the aggregator's idempotency is the resilience guarantee).
 *
 * **Orphan events (Pitfall 5 mitigation — must_haves.key_links #3):**
 *   - When a CRCON event payload carries a `steam_id_64` for which no Player
 *     row exists in the league (e.g. a player not in the clan league played
 *     on the booked server, or admin missed the Steam ID backfill), the
 *     event is silently skipped. No exception, no log spam. The threat
 *     model treats this as expected: orphan events ARE part of the stream
 *     because CRCON sees every player on the server, not just league
 *     members. Round-1 mitigation: admins backfill steam_id_64 on Player
 *     profiles before booked matches.
 *
 * **Wrapping DB::transaction (Postgres SAVEPOINT idiom from plan 08-07):**
 *   - The aggregate read + per-player upsert loop lives inside a single
 *     `DB::transaction`. RefreshDatabase wraps each Pest test in an outer
 *     transaction; nesting issues a SAVEPOINT/RELEASE pair which serialises
 *     the read+write so a concurrent CloseMatchJob retry doesn't see a
 *     half-finished upsert. In production (no outer transaction) the
 *     wrapping `DB::transaction` becomes a real BEGIN/COMMIT — same
 *     semantic guarantee.
 *
 * Stateless `final` — auto-resolved by the Laravel container; constructor-
 * injected into CloseMatchJob::handle().
 */
final class MatchPlayerStatAggregator
{
    /**
     * Roll up `match_events` for `$match` into `match_player_stats` rows.
     *
     * Reads the 5 player-related event types (`player_kill`,
     * `player_team_kill`, `player_connect`, `player_disconnect`,
     * `team_switch`) and computes per-player counters: kills, deaths,
     * team_kills, score (derived = kills × 100; CRCON does not emit
     * per-player score in /ws/logs), weapons_used jsonb histogram.
     *
     * @return int Number of MatchPlayerStat rows upserted (excludes orphan
     *             events whose steam_id_64 has no matching Player row).
     */
    public function aggregate(GameMatch $match): int
    {
        return DB::transaction(function () use ($match): int {
            $events = MatchEvent::where('match_id', $match->id)
                ->whereIn('event_type', [
                    'player_kill',
                    'player_team_kill',
                    'player_connect',
                    'player_disconnect',
                    'team_switch',
                ])
                ->get();

            /** @var array<string, array<string, int|array<string, int>>> $perPlayer */
            $perPlayer = [];

            foreach ($events as $event) {
                // Cast on MatchEvent::$casts gives us an array — PHPStan's inference
                // can't see through the cast (the column is jsonb on the DB side, text
                // through Larastan's schema-aware inference). Annotate explicitly.
                /** @var array<string, mixed> $payload */
                $payload = $event->payload;

                switch ($event->event_type) {
                    case 'player_kill':
                        $killerSteam = $payload['killer']['steam_id_64'] ?? null;
                        $victimSteam = $payload['victim']['steam_id_64'] ?? null;
                        if (is_string($killerSteam) && $killerSteam !== '') {
                            $perPlayer[$killerSteam]['kills'] = ($perPlayer[$killerSteam]['kills'] ?? 0) + 1;
                            $weapon = $payload['weapon'] ?? 'unknown';
                            if (is_string($weapon) && $weapon !== '') {
                                $weaponsUsed = $perPlayer[$killerSteam]['weapons_used'] ?? [];
                                $weaponsUsed[$weapon] = ($weaponsUsed[$weapon] ?? 0) + 1;
                                $perPlayer[$killerSteam]['weapons_used'] = $weaponsUsed;
                            }
                        }
                        if (is_string($victimSteam) && $victimSteam !== '') {
                            $perPlayer[$victimSteam]['deaths'] = ($perPlayer[$victimSteam]['deaths'] ?? 0) + 1;
                        }
                        break;

                    case 'player_team_kill':
                        $killerSteam = $payload['killer']['steam_id_64'] ?? null;
                        if (is_string($killerSteam) && $killerSteam !== '') {
                            $perPlayer[$killerSteam]['team_kills'] = ($perPlayer[$killerSteam]['team_kills'] ?? 0) + 1;
                        }
                        break;

                        // player_connect / player_disconnect / team_switch:
                        // round-1 simply ensures the player row would exist if seen.
                        // Future plans may derive role_played from team_switch — left
                        // un-recorded here to avoid premature schema coupling.
                    default:
                        break;
                }
            }

            $upserted = 0;
            foreach ($perPlayer as $steamId => $stats) {
                $player = Player::firstWhere('steam_id_64', $steamId);
                if ($player === null) {
                    // Pitfall 5 — orphan event (CRCON saw a non-league player on
                    // the server, or admin missed the Steam-ID backfill).
                    // Silently skip; no exception, no log spam.
                    continue;
                }

                $kills = (int) ($stats['kills'] ?? 0);
                $deaths = (int) ($stats['deaths'] ?? 0);
                $teamKills = (int) ($stats['team_kills'] ?? 0);
                /** @var array<string, int>|null $weaponsUsed */
                $weaponsUsed = $stats['weapons_used'] ?? null;

                MatchPlayerStat::updateOrCreate(
                    [
                        'match_id' => $match->id,
                        'player_id' => $player->id,
                    ],
                    [
                        'kills' => $kills,
                        'deaths' => $deaths,
                        'team_kills' => $teamKills,
                        // Derived: CRCON /ws/logs does not emit per-player score;
                        // round-1 proxy is kills × 100 (well documented in
                        // 08-RESEARCH.md scoring section).
                        'score' => $kills * 100,
                        'weapons_used' => $weaponsUsed,
                    ],
                );
                $upserted++;
            }

            return $upserted;
        });
    }
}
