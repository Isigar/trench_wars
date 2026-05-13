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
 * Burton-variant double-elim placement order:
 *   - rank 1 → grand-final winner
 *   - rank 2 → grand-final loser
 *   - rank 3 → L-bracket final loser
 *   - rank 4 → L-bracket semi loser (round-(lRounds-1) loser)
 *   - rank N → losers in descending L-bracket round
 *
 * Wins/losses are counted from all bracket-linked MatchResult rows the
 * participant played in either bracket (W or L). The grand-final reset, when
 * present, is round 2 of the grand-final stage.
 *
 * Standings rows are written against the GRAND-FINAL stage (single canonical
 * stage_id for the tournament). The W / L stages contribute W/L counts but
 * are NOT used as the standings row's stage_id — keeps queries simple for
 * the public Standings tab (plan 06-12) and the Filament "Recalculate
 * standings" action (plan 06-11).
 */
final class DoubleEliminationStandingsCalculator implements StandingsCalculatorStrategy
{
    public function compute(Tournament $tournament): void
    {
        /** @var TournamentStage|null $wStage */
        $wStage = $tournament->stages()->where('type', 'winners-bracket')->first();
        /** @var TournamentStage|null $lStage */
        $lStage = $tournament->stages()->where('type', 'losers-bracket')->first();
        /** @var TournamentStage|null $gfStage */
        $gfStage = $tournament->stages()->where('type', 'grand-final')->first();

        if ($gfStage === null) {
            return;
        }

        $stages = collect([$wStage, $lStage, $gfStage])->filter()->values();

        $participants = $tournament->participants()
            ->whereIn('status', ['active', 'withdrawn', 'disqualified'])
            ->get();

        // Compute W/L counts per participant (across all 3 stages).
        $stats = [];
        foreach ($participants as $p) {
            $stats[$p->id] = ['wins' => 0, 'losses' => 0];
        }

        foreach ($stages as $stage) {
            foreach ($stage->brackets()->whereNotNull('match_id')->get() as $bracket) {
                /** @var TournamentBracket $bracket */
                $result = MatchResult::query()
                    ->where('match_id', $bracket->match_id)
                    ->whereNotNull('winner_clan_id')
                    ->first();
                if ($result === null) {
                    continue;
                }

                foreach ([$bracket->participant_a_id, $bracket->participant_b_id] as $pid) {
                    if ($pid === null || ! array_key_exists($pid, $stats)) {
                        continue;
                    }
                    $participantClanId = $participants->firstWhere('id', $pid)?->clan_id;
                    if ($participantClanId === $result->winner_clan_id) {
                        $stats[$pid]['wins']++;
                    } else {
                        $stats[$pid]['losses']++;
                    }
                }
            }
        }

        // Placement: walk grand-final → L-bracket-final → L-bracket-round-N
        // backwards. Track which participant landed at which rank.
        $ranks = $this->computePlacements($tournament, $gfStage, $lStage);

        foreach ($participants as $participant) {
            $s = $stats[$participant->id];
            $rank = $ranks[$participant->id] ?? null;

            TournamentStanding::create([
                'tournament_id' => $tournament->id,
                'tournament_stage_id' => $gfStage->id,
                'participant_id' => $participant->id,
                'wins' => $s['wins'],
                'losses' => $s['losses'],
                'draws' => 0,
                'points' => $s['wins'],
                'tiebreak_score' => 0,
                'rank' => $rank,
            ]);
        }
    }

    /**
     * Walk GF (rank 1, 2) → L-final (rank 3) → L-(rounds-1) → ... → L-1 to
     * assign placements. Participants who lost in earlier L-rounds get higher
     * (worse) numeric ranks.
     *
     * @return array<string, int>
     */
    private function computePlacements(
        Tournament $tournament,
        TournamentStage $gfStage,
        ?TournamentStage $lStage,
    ): array {
        $ranks = [];

        // Grand final: prefer the reset match (round 2) when present.
        /** @var TournamentBracket|null $finalDecider */
        $finalDecider = $gfStage->brackets()
            ->whereNotNull('winner_participant_id')
            ->orderByDesc('round_number')
            ->first();

        if ($finalDecider !== null && $finalDecider->winner_participant_id !== null) {
            $winner = $finalDecider->winner_participant_id;
            $loser = $finalDecider->participant_a_id === $winner
                ? $finalDecider->participant_b_id
                : $finalDecider->participant_a_id;
            $ranks[$winner] = 1;
            if ($loser !== null) {
                $ranks[$loser] = 2;
            }
        }

        // L-bracket placements: walk round-N (L-final) down to round 1.
        if ($lStage !== null) {
            $lRounds = (int) ($lStage->brackets()->max('round_number') ?? 0);
            $nextRank = 3;

            for ($r = $lRounds; $r >= 1; $r--) {
                $brackets = $lStage->brackets()
                    ->where('round_number', $r)
                    ->orderBy('position')
                    ->get();

                $losersThisRound = [];
                foreach ($brackets as $b) {
                    /** @var TournamentBracket $b */
                    if ($b->winner_participant_id === null) {
                        continue;
                    }
                    $loserPid = $b->participant_a_id === $b->winner_participant_id
                        ? $b->participant_b_id
                        : $b->participant_a_id;
                    if ($loserPid !== null && ! array_key_exists($loserPid, $ranks)) {
                        $losersThisRound[] = $loserPid;
                    }
                }

                // All losers in the same L-round share the same rank
                // (Phase 9 polish: break by seed for distinct ranks).
                foreach ($losersThisRound as $loserPid) {
                    $ranks[$loserPid] = $nextRank;
                }
                if ($losersThisRound !== []) {
                    $nextRank += count($losersThisRound);
                }
            }
        }

        unset($tournament);

        return $ranks;
    }
}
