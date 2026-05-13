<?php

declare(strict_types=1);

namespace App\Services\Standings;

use App\Models\MatchResult;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentStanding;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-09-PLAN.md <interfaces> +
 *         06-RESEARCH.md Pattern 5 (Swiss with Buchholz tiebreak).
 *
 * Chess-style points: 1 per win, 0.5 per draw, 0 per loss; bye = 1 point.
 *
 * Tiebreaker = Buchholz (plain): sum of each participant's opponents' current
 * scores (computed AFTER all rounds, not per-round). Median Buchholz variant
 * is Phase 9 polish.
 *
 * Sort order: (points DESC, buchholz DESC, seed ASC).
 *
 * Includes withdrawn / disqualified participants (A5 LOCKED). All swiss-round
 * stages are aggregated into a single standings table keyed by the first
 * swiss-round stage's id (stable canonical stage_id for the public Standings
 * tab + Filament admin Recalculate action).
 */
final class SwissStandingsCalculator implements StandingsCalculatorStrategy
{
    public function compute(Tournament $tournament): void
    {
        $stages = $tournament->stages()->where('type', 'swiss-round')->get();
        if ($stages->isEmpty()) {
            return;
        }

        $participants = $tournament->participants()
            ->whereIn('status', ['active', 'withdrawn', 'disqualified'])
            ->get();

        $clanByParticipant = [];
        foreach ($participants as $p) {
            $clanByParticipant[$p->id] = $p->clan_id;
        }

        /** @var array<string, float> $pointsByParticipant */
        $pointsByParticipant = [];
        /** @var array<string, array<int, string>> $opponentsByParticipant */
        $opponentsByParticipant = [];
        /** @var array<string, int> $wins */
        $wins = [];
        /** @var array<string, int> $losses */
        $losses = [];
        /** @var array<string, int> $draws */
        $draws = [];
        foreach ($participants as $p) {
            $pointsByParticipant[$p->id] = 0.0;
            $opponentsByParticipant[$p->id] = [];
            $wins[$p->id] = 0;
            $losses[$p->id] = 0;
            $draws[$p->id] = 0;
        }

        // Collect all match results across all swiss-round stages in one query.
        $matchIds = collect();
        foreach ($stages as $stage) {
            $matchIds = $matchIds->merge(
                $stage->brackets()->whereNotNull('match_id')->pluck('match_id')
            );
        }
        $results = MatchResult::query()
            ->whereIn('match_id', $matchIds)
            ->get()
            ->keyBy('match_id');

        // First pass: points + opponent collection.
        foreach ($stages as $stage) {
            foreach ($stage->brackets()->get() as $bracket) {
                /** @var TournamentBracket $bracket */
                $pAId = $bracket->participant_a_id;
                $pBId = $bracket->participant_b_id;

                // Bye: only one side populated AND winner pre-assigned by the generator.
                if ($pAId !== null && $pBId === null && $bracket->winner_participant_id === $pAId) {
                    if (array_key_exists($pAId, $pointsByParticipant)) {
                        $pointsByParticipant[$pAId] += 1.0;
                        $wins[$pAId]++;
                    }

                    continue;
                }
                if ($pBId !== null && $pAId === null && $bracket->winner_participant_id === $pBId) {
                    if (array_key_exists($pBId, $pointsByParticipant)) {
                        $pointsByParticipant[$pBId] += 1.0;
                        $wins[$pBId]++;
                    }

                    continue;
                }

                if ($pAId === null || $pBId === null) {
                    continue;
                }

                if (array_key_exists($pAId, $opponentsByParticipant)) {
                    $opponentsByParticipant[$pAId][] = $pBId;
                }
                if (array_key_exists($pBId, $opponentsByParticipant)) {
                    $opponentsByParticipant[$pBId][] = $pAId;
                }

                if ($bracket->match_id === null) {
                    continue;
                }
                $result = $results->get($bracket->match_id);
                if ($result === null) {
                    continue;
                }

                if ($result->winner_clan_id === null) {
                    if (array_key_exists($pAId, $pointsByParticipant)) {
                        $pointsByParticipant[$pAId] += 0.5;
                        $draws[$pAId]++;
                    }
                    if (array_key_exists($pBId, $pointsByParticipant)) {
                        $pointsByParticipant[$pBId] += 0.5;
                        $draws[$pBId]++;
                    }
                } elseif ($result->winner_clan_id === ($clanByParticipant[$pAId] ?? null)) {
                    if (array_key_exists($pAId, $pointsByParticipant)) {
                        $pointsByParticipant[$pAId] += 1.0;
                        $wins[$pAId]++;
                    }
                    if (array_key_exists($pBId, $losses)) {
                        $losses[$pBId]++;
                    }
                } else {
                    if (array_key_exists($pBId, $pointsByParticipant)) {
                        $pointsByParticipant[$pBId] += 1.0;
                        $wins[$pBId]++;
                    }
                    if (array_key_exists($pAId, $losses)) {
                        $losses[$pAId]++;
                    }
                }
            }
        }

        // Second pass: Buchholz = sum of each participant's opponents' final points.
        /** @var array<string, float> $buchholzByParticipant */
        $buchholzByParticipant = [];
        foreach ($participants as $p) {
            $sum = 0.0;
            foreach ($opponentsByParticipant[$p->id] as $oppId) {
                $sum += $pointsByParticipant[$oppId] ?? 0.0;
            }
            $buchholzByParticipant[$p->id] = $sum;
        }

        $rows = [];
        foreach ($participants as $p) {
            $rows[] = [
                'participant' => $p,
                'wins' => $wins[$p->id],
                'losses' => $losses[$p->id],
                'draws' => $draws[$p->id],
                'points' => $pointsByParticipant[$p->id],
                'buchholz' => $buchholzByParticipant[$p->id],
            ];
        }

        usort($rows, function (array $a, array $b): int {
            if ((float) $a['points'] !== (float) $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            if ((float) $a['buchholz'] !== (float) $b['buchholz']) {
                return $b['buchholz'] <=> $a['buchholz'];
            }
            $aSeed = $a['participant']->seed ?? PHP_INT_MAX;
            $bSeed = $b['participant']->seed ?? PHP_INT_MAX;

            return $aSeed <=> $bSeed;
        });

        // The `$stages->isEmpty()` early return above guarantees first() is non-null.
        $primaryStage = $stages->first();

        foreach ($rows as $index => $row) {
            TournamentStanding::create([
                'tournament_id' => $tournament->id,
                'tournament_stage_id' => $primaryStage->id,
                'participant_id' => $row['participant']->id,
                'wins' => $row['wins'],
                'losses' => $row['losses'],
                'draws' => $row['draws'],
                'points' => $row['points'],
                'tiebreak_score' => $row['buchholz'],
                'rank' => $index + 1,
            ]);
        }
    }
}
