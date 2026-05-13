<?php

declare(strict_types=1);

namespace App\Services\Standings;

use App\Models\MatchResult;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-09-PLAN.md <interfaces>.
 *
 * Placement is derived from the bracket round in which the participant was
 * eliminated. Formula:
 *
 *   placement(eliminated in round R) = 2 ** (finalRound - R) + 1
 *
 * For an 8-team bracket (finalRound=3):
 *   - Final winner       → rank 1
 *   - Final loser        → rank 2
 *   - Semifinal losers   → rank 3 (shared by both — Phase 9 polish breaks by seed)
 *   - QF losers          → rank 5 (shared by all four)
 *
 * Wins/losses are computed from MatchResult rows linked to each participant's
 * brackets. Single-elim has no draws (single decisive match per bracket); each
 * `points` value equals `wins` (1 per win).
 *
 * Withdrawn / disqualified participants are INCLUDED — A5 LOCKED: past matches
 * retain their results; rank reflects performance up to withdrawal time.
 */
final class SingleEliminationStandingsCalculator implements StandingsCalculatorStrategy
{
    public function compute(Tournament $tournament): void
    {
        /** @var TournamentStage|null $stage */
        $stage = $tournament->stages()->where('type', 'elim')->first();
        if ($stage === null) {
            return;
        }

        $finalRound = (int) ($stage->brackets()->max('round_number') ?? 0);
        /** @var TournamentBracket|null $finalBracket */
        $finalBracket = $stage->brackets()
            ->where('round_number', $finalRound)
            ->where('position', 1)
            ->first();

        $participants = $tournament->participants()
            ->whereIn('status', ['active', 'withdrawn', 'disqualified'])
            ->get();

        foreach ($participants as $participant) {
            [$wins, $losses] = $this->countWinsLosses($stage, $participant);

            $rank = $this->derivePlacement($stage, $participant, $finalRound, $finalBracket);

            TournamentStanding::create([
                'tournament_id' => $tournament->id,
                'tournament_stage_id' => $stage->id,
                'participant_id' => $participant->id,
                'wins' => $wins,
                'losses' => $losses,
                'draws' => 0,
                'points' => $wins,
                'tiebreak_score' => 0,
                'rank' => $rank,
            ]);
        }
    }

    /**
     * Count wins + losses from MatchResult rows linked to brackets where the
     * participant played. Byes (no match_id) and undecided matches are skipped.
     *
     * @return array{0:int,1:int}
     */
    private function countWinsLosses(TournamentStage $stage, TournamentParticipant $participant): array
    {
        $matchIds = $stage->brackets()
            ->where(function ($q) use ($participant): void {
                $q->where('participant_a_id', $participant->id)
                    ->orWhere('participant_b_id', $participant->id);
            })
            ->whereNotNull('match_id')
            ->pluck('match_id');

        if ($matchIds->isEmpty()) {
            return [0, 0];
        }

        $results = MatchResult::query()
            ->whereIn('match_id', $matchIds)
            ->whereNotNull('winner_clan_id')
            ->get();

        $wins = 0;
        $losses = 0;
        foreach ($results as $result) {
            if ($result->winner_clan_id === $participant->clan_id) {
                $wins++;
            } else {
                $losses++;
            }
        }

        return [$wins, $losses];
    }

    /**
     * Walk the bracket tree backwards to find the round in which the
     * participant was eliminated; map to placement using the
     * `2^(finalRound - R) + 1` formula. Tournament winner returns 1; final
     * loser returns 2.
     *
     * Bye-only participants (never appeared in a played bracket) return null.
     */
    private function derivePlacement(
        TournamentStage $stage,
        TournamentParticipant $participant,
        int $finalRound,
        ?TournamentBracket $finalBracket,
    ): ?int {
        if ($finalBracket !== null && $finalBracket->winner_participant_id === $participant->id) {
            return 1;
        }

        if (
            $finalBracket !== null
            && ($finalBracket->participant_a_id === $participant->id
                || $finalBracket->participant_b_id === $participant->id)
            && $finalBracket->winner_participant_id !== null
        ) {
            return 2;
        }

        /** @var TournamentBracket|null $lastBracket */
        $lastBracket = $stage->brackets()
            ->where(function ($q) use ($participant): void {
                $q->where('participant_a_id', $participant->id)
                    ->orWhere('participant_b_id', $participant->id);
            })
            ->reorder()
            ->orderByDesc('round_number')
            ->first();

        if ($lastBracket === null) {
            return null;
        }

        // If their last appearance has no decided winner yet, they're still "in"
        // the bracket — leave rank null until the bracket completes.
        if ($lastBracket->winner_participant_id === null) {
            return null;
        }

        // Eliminated in round R → placement = 2^(finalRound - R) + 1
        $roundEliminated = (int) $lastBracket->round_number;
        $offset = $finalRound - $roundEliminated;
        if ($offset < 0) {
            return null;
        }

        return (2 ** $offset) + 1;
    }
}
