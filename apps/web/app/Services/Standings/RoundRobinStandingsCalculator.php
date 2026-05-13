<?php

declare(strict_types=1);

namespace App\Services\Standings;

use App\Models\MatchResult;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-09-PLAN.md <interfaces>.
 *
 * FIFA-standard points: 3 per win, 1 per draw, 0 per loss (admin override via
 * tournament.settings.roundrobin_points_per_{win,draw,loss}).
 *
 * Tiebreaker order (LOCKED inline in plan 06-09):
 *   1. points DESC
 *   2. head-to-head wins among the tied pair DESC (v1: simple direct h2h;
 *      Phase 9 polish wraps a mini-table for transitive 3-way ties)
 *   3. point_differential DESC
 *   4. seed ASC
 *
 * Includes withdrawn / disqualified participants (A5 LOCKED): past matches
 * retain their results in the standings; the tiebreak still applies to all
 * registered participants. Their rank reflects performance through the point
 * of withdrawal.
 */
final class RoundRobinStandingsCalculator implements StandingsCalculatorStrategy
{
    public function compute(Tournament $tournament): void
    {
        /** @var TournamentStage|null $stage */
        $stage = $tournament->stages()->where('type', 'group')->first();
        if ($stage === null) {
            return;
        }

        /** @var array<string, mixed>|null $settings */
        $settings = $tournament->settings;
        $pointsWin = (int) ($settings['roundrobin_points_per_win'] ?? 3);
        $pointsDraw = (int) ($settings['roundrobin_points_per_draw'] ?? 1);
        $pointsLoss = (int) ($settings['roundrobin_points_per_loss'] ?? 0);

        $participants = $tournament->participants()
            ->whereIn('status', ['active', 'withdrawn', 'disqualified'])
            ->get();

        // Participant id → clan id map (Phase 9 polish: preload via with()).
        $clanByParticipant = [];
        foreach ($participants as $p) {
            $clanByParticipant[$p->id] = $p->clan_id;
        }

        // Per-participant counters. Initialised to 0 for every registered
        // participant; updated as we iterate played brackets.
        /** @var array<string, int> $wins */
        $wins = [];
        /** @var array<string, int> $losses */
        $losses = [];
        /** @var array<string, int> $draws */
        $draws = [];
        /** @var array<string, int> $pointDiff */
        $pointDiff = [];
        /** @var array<string, array<string, int>> $h2h */
        $h2h = [];
        foreach ($participants as $p) {
            $wins[$p->id] = 0;
            $losses[$p->id] = 0;
            $draws[$p->id] = 0;
            $pointDiff[$p->id] = 0;
            $h2h[$p->id] = [];
        }

        $brackets = $stage->brackets()->whereNotNull('match_id')->get();
        $matchIds = $brackets->pluck('match_id');
        $results = MatchResult::query()
            ->whereIn('match_id', $matchIds)
            ->get()
            ->keyBy('match_id');

        foreach ($brackets as $bracket) {
            /** @var TournamentBracket $bracket */
            $result = $results->get($bracket->match_id);
            if ($result === null) {
                continue;
            }

            $pAId = $bracket->participant_a_id;
            $pBId = $bracket->participant_b_id;
            if ($pAId === null || $pBId === null) {
                continue;
            }
            if (! array_key_exists($pAId, $wins) || ! array_key_exists($pBId, $wins)) {
                continue;
            }

            $aScore = (int) ($result->allies_score ?? 0);
            $bScore = (int) ($result->axis_score ?? 0);

            if ($result->winner_clan_id === null) {
                $draws[$pAId]++;
                $draws[$pBId]++;
            } elseif ($result->winner_clan_id === $clanByParticipant[$pAId]) {
                $wins[$pAId]++;
                $losses[$pBId]++;
                $h2h[$pAId][$pBId] = ($h2h[$pAId][$pBId] ?? 0) + 1;
            } else {
                $wins[$pBId]++;
                $losses[$pAId]++;
                $h2h[$pBId][$pAId] = ($h2h[$pBId][$pAId] ?? 0) + 1;
            }

            $pointDiff[$pAId] += $aScore - $bScore;
            $pointDiff[$pBId] += $bScore - $aScore;
        }

        $rows = [];
        foreach ($participants as $p) {
            $points = $wins[$p->id] * $pointsWin + $draws[$p->id] * $pointsDraw + $losses[$p->id] * $pointsLoss;
            $rows[] = [
                'participant' => $p,
                'wins' => $wins[$p->id],
                'losses' => $losses[$p->id],
                'draws' => $draws[$p->id],
                'points' => $points,
                'point_diff' => $pointDiff[$p->id],
                'head_to_head' => $h2h[$p->id],
            ];
        }

        // Sort by (points DESC, head-to-head wins DESC, point_diff DESC, seed ASC).
        usort($rows, function (array $a, array $b): int {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            $aH2H = $a['head_to_head'][$b['participant']->id] ?? 0;
            $bH2H = $b['head_to_head'][$a['participant']->id] ?? 0;
            if ($aH2H !== $bH2H) {
                return $bH2H <=> $aH2H;
            }
            if ($a['point_diff'] !== $b['point_diff']) {
                return $b['point_diff'] <=> $a['point_diff'];
            }
            $aSeed = $a['participant']->seed ?? PHP_INT_MAX;
            $bSeed = $b['participant']->seed ?? PHP_INT_MAX;

            return $aSeed <=> $bSeed;
        });

        foreach ($rows as $index => $row) {
            TournamentStanding::create([
                'tournament_id' => $tournament->id,
                'tournament_stage_id' => $stage->id,
                'participant_id' => $row['participant']->id,
                'wins' => $row['wins'],
                'losses' => $row['losses'],
                'draws' => $row['draws'],
                'points' => $row['points'],
                'tiebreak_score' => $row['point_diff'],
                'rank' => $index + 1,
            ]);
        }
    }
}
