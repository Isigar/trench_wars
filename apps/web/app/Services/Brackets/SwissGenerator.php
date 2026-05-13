<?php

declare(strict_types=1);

namespace App\Services\Brackets;

use App\Exceptions\SwissTooFewParticipantsException;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md Pattern 5
 * (Swiss with Buchholz tiebreak) + 06-07-PLAN.md <interfaces>.
 *
 * Ships in plan 06-07 (Wave 4); replaces the LogicException stub from plan 06-06.
 *
 * Generation model (Open Question A6 RESOLVED LOCKED inline):
 *   - generate() ships ROUND 1 ONLY.
 *   - Round 2+ requires admin click via generateNextRound() — wired in
 *     plan 06-11 as a Filament HeaderAction on TournamentResource.
 *
 * Round 1 pairing:
 *   - Top half vs bottom half by seed. 8 participants → (1v5), (2v6), (3v7), (4v8).
 *   - Odd N: lowest-seed participant gets a bye in round 1 (winner_participant_id
 *     pre-assigned).
 *
 * Round 2+ pairing (generateNextRound):
 *   - Read standings (plan 06-09 will populate; for v1 tests fixture rows manually).
 *   - Sort by (points DESC, tiebreak_score DESC, seed ASC).
 *   - Group by points → within each group: top half vs bottom half.
 *   - Float-down: odd-count groups pop their bottom into the next score group.
 *   - Never-paired-before constraint: swap-down with next candidate on conflict;
 *     log warning on hard duplicate (rare for N <= 64).
 *
 * Pitfall 5 mitigation (T-06-07-01):
 *   Refuse generate() when participants_count < 2^ceil(log2(N)). For 3
 *   participants × ceil(log2(3))=2 rounds, 2^2=4 minimum > 3 → throw
 *   SwissTooFewParticipantsException with localised message.
 *
 * Storage shape:
 *   - 1 stage per round (type='swiss-round', ordinal=round-number).
 *   - tournament_brackets rows with round_number=1 within each stage
 *     (the in-stage round counter is always 1 since each swiss-round stage
 *     holds exactly one logical round; the tournament round counter is the
 *     stage.ordinal).
 *   - advances_to_bracket_id is NULL for every swiss bracket (Buchholz reads
 *     standings, not advance chains).
 *
 * Threat refs:
 *   - T-06-07-01 (swiss backtrack infinite loop)   — mitigate
 *   - T-06-07-04 (generateNextRound partial state) — mitigate via caller's
 *     DB::transaction (Filament Action will wrap the call)
 */
final class SwissGenerator implements BracketGeneratorStrategy
{
    /**
     * Generates round 1 only. Open Question A6 RESOLVED LOCKED inline: admin-click
     * next-round via generateNextRound() (plan 06-11 Filament Action).
     *
     * @param  Collection<int, TournamentParticipant>  $orderedParticipants
     */
    public function generate(Tournament $tournament, Collection $orderedParticipants): void
    {
        $n = $orderedParticipants->count();
        $rounds = max(1, (int) ceil(log(max($n, 2), 2)));

        // Pitfall 5: refuse to start swiss with too few participants for the
        // required round count. 2^rounds is the lower bound.
        $minRequired = 2 ** $rounds;
        if ($n < $minRequired) {
            throw new SwissTooFewParticipantsException(
                (string) __('tournaments.errors.swiss_too_few_participants', [
                    'min' => $minRequired,
                    'rounds' => $rounds,
                ])
            );
        }

        $stage = TournamentStage::create([
            'tournament_id' => $tournament->id,
            'type' => 'swiss-round',
            'ordinal' => 1,
            'name' => (string) __('tournaments.stage_types.swiss-round.label') . ' 1',
            'settings' => null,
        ]);

        // Round 1 pairing: top half vs bottom half by seed.
        // 8 participants: (1v5), (2v6), (3v7), (4v8).
        $half = (int) ($n / 2);
        $topHalf = $orderedParticipants->slice(0, $half)->values();
        $bottomHalf = $orderedParticipants->slice($half, $half)->values();

        for ($i = 0; $i < $half; $i++) {
            /** @var TournamentParticipant $pA */
            $pA = $topHalf[$i];
            /** @var TournamentParticipant $pB */
            $pB = $bottomHalf[$i];
            TournamentBracket::create([
                'tournament_stage_id' => $stage->id,
                'round_number' => 1,
                'position' => $i + 1,
                'participant_a_id' => $pA->id,
                'participant_b_id' => $pB->id,
                'winner_participant_id' => null,
                'match_id' => null,
                'advances_to_bracket_id' => null,
                'loser_advances_to_bracket_id' => null,
            ]);
        }

        // Odd N: lowest-seed gets a bye in round 1 (winner_participant_id auto-set).
        if ($n % 2 === 1) {
            /** @var TournamentParticipant|null $byeParticipant */
            $byeParticipant = $orderedParticipants->last();
            if ($byeParticipant !== null) {
                TournamentBracket::create([
                    'tournament_stage_id' => $stage->id,
                    'round_number' => 1,
                    'position' => $half + 1,
                    'participant_a_id' => $byeParticipant->id,
                    'participant_b_id' => null,
                    'winner_participant_id' => $byeParticipant->id,
                    'match_id' => null,
                    'advances_to_bracket_id' => null,
                    'loser_advances_to_bracket_id' => null,
                ]);
            }
        }
    }

    /**
     * Generates the next swiss-round stage. Admin-click triggered via Filament
     * (Open Question A6 RESOLVED LOCKED inline). Pairs by (points DESC,
     * tiebreak_score DESC, seed ASC); top half vs bottom half within score
     * groups; never-paired-before swap-down on conflict; odd-group float-down.
     *
     * Pre-conditions:
     *   - Round 1 has been generated by generate() and at least one stage with
     *     type='swiss-round' exists.
     *   - tournament_standings rows exist for every active participant (plan 06-09).
     *
     * @throws LogicException when all swiss rounds have been generated
     */
    public function generateNextRound(Tournament $tournament): void
    {
        $currentRound = $tournament->stages()->where('type', 'swiss-round')->max('ordinal');
        $activeCount = $tournament->participants()->where('status', 'active')->count();
        $totalRounds = max(1, (int) ceil(log(max($activeCount, 2), 2)));

        if ($currentRound === null || $currentRound >= $totalRounds) {
            throw new LogicException(
                (string) __('tournaments.errors.swiss_rounds_exhausted')
            );
        }

        $nextOrdinal = (int) $currentRound + 1;

        // Fetch standings sorted by (points DESC, tiebreak_score DESC, seed ASC).
        // The seed-tiebreaker uses a join against tournament_participants.
        $standings = $tournament->standings()
            ->with('participant')
            ->orderBy('points', 'desc')
            ->orderBy('tiebreak_score', 'desc')
            ->get()
            ->sortBy(fn ($s): int => (int) ($s->participant->seed ?? PHP_INT_MAX))
            ->sortByDesc(fn ($s): float => (float) $s->tiebreak_score)
            ->sortByDesc(fn ($s): float => (float) $s->points)
            ->values();

        // Group by points (cast to string for consistent key collation).
        $grouped = $standings->groupBy(fn ($s): string => (string) $s->points);

        $alreadyPaired = $this->getAlreadyPairedSet($tournament);

        /** @var list<array{0: TournamentParticipant, 1: TournamentParticipant}> $pairings */
        $pairings = [];
        $floatDown = null;

        foreach ($grouped as $group) {
            /** @var Collection<int, mixed> $groupCol */
            $groupCol = collect($group->all());
            if ($floatDown !== null) {
                $groupCol->prepend($floatDown);
                $floatDown = null;
            }
            if ($groupCol->count() % 2 === 1) {
                $floatDown = $groupCol->pop();
            }
            $half = (int) ($groupCol->count() / 2);
            $top = $groupCol->slice(0, $half)->values();
            $bot = $groupCol->slice($half, $half)->values();

            for ($i = 0; $i < $half; $i++) {
                /** @var TournamentParticipant $pA */
                $pA = $top[$i]->participant;
                /** @var TournamentParticipant $pB */
                $pB = $bot[$i]->participant;
                $pairKey = $this->pairKey($pA->id, $pB->id);

                if (isset($alreadyPaired[$pairKey]) && $i + 1 < $half) {
                    // Simple swap with the next bottom candidate.
                    [$bot[$i], $bot[$i + 1]] = [$bot[$i + 1], $bot[$i]];
                    $pB = $bot[$i]->participant;
                    $pairKey = $this->pairKey($pA->id, $pB->id);
                }
                if (isset($alreadyPaired[$pairKey])) {
                    Log::warning("Swiss pairing duplicate detected — tournament {$tournament->id} round {$nextOrdinal}: {$pA->id} vs {$pB->id}");
                }
                $pairings[] = [$pA, $pB];
                $alreadyPaired[$pairKey] = true;
            }
        }

        /** @var TournamentParticipant|null $byeParticipant */
        $byeParticipant = null;
        if ($floatDown !== null) {
            $byeParticipant = $floatDown->participant;
        }

        $stage = TournamentStage::create([
            'tournament_id' => $tournament->id,
            'type' => 'swiss-round',
            'ordinal' => $nextOrdinal,
            'name' => (string) __('tournaments.stage_types.swiss-round.label') . ' ' . $nextOrdinal,
            'settings' => null,
        ]);

        foreach ($pairings as $i => [$pA, $pB]) {
            TournamentBracket::create([
                'tournament_stage_id' => $stage->id,
                'round_number' => 1,
                'position' => $i + 1,
                'participant_a_id' => $pA->id,
                'participant_b_id' => $pB->id,
                'winner_participant_id' => null,
                'match_id' => null,
                'advances_to_bracket_id' => null,
                'loser_advances_to_bracket_id' => null,
            ]);
        }

        if ($byeParticipant !== null) {
            TournamentBracket::create([
                'tournament_stage_id' => $stage->id,
                'round_number' => 1,
                'position' => count($pairings) + 1,
                'participant_a_id' => $byeParticipant->id,
                'participant_b_id' => null,
                'winner_participant_id' => $byeParticipant->id,
                'match_id' => null,
                'advances_to_bracket_id' => null,
                'loser_advances_to_bracket_id' => null,
            ]);
        }
    }

    /**
     * Build the set of already-paired participant pairs (across ALL prior
     * swiss-round stages) keyed by "{smaller_id}-{larger_id}".
     *
     * @return array<string, bool>
     */
    private function getAlreadyPairedSet(Tournament $tournament): array
    {
        $set = [];
        $stageIds = $tournament->stages()
            ->where('type', 'swiss-round')
            ->pluck('id');
        if ($stageIds->isEmpty()) {
            return $set;
        }
        $brackets = TournamentBracket::query()
            ->whereIn('tournament_stage_id', $stageIds)
            ->get();
        foreach ($brackets as $b) {
            if ($b->participant_a_id !== null && $b->participant_b_id !== null) {
                $set[$this->pairKey($b->participant_a_id, $b->participant_b_id)] = true;
            }
        }

        return $set;
    }

    private function pairKey(string $a, string $b): string
    {
        return $a < $b ? "{$a}-{$b}" : "{$b}-{$a}";
    }
}
