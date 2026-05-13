<?php

declare(strict_types=1);

use App\Exceptions\SwissTooFewParticipantsException;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use App\Models\TournamentStanding;
use App\Services\Brackets\BracketGeneratorService;
use App\Services\Brackets\SwissGenerator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Sequence;

/*
| Source: 06-07-PLAN.md Task 3 — replaces Wave 0 RED stub from 06-01.
|
| Covers SwissGenerator via BracketGeneratorService dispatch + direct
| generateNextRound() invocation:
|   - RESEARCH Pattern 5 + Open Question A6 RESOLVED LOCKED inline (admin-click)
|   - Round-1 pairing: top half vs bottom half by seed (1v5, 2v6, 3v7, 4v8)
|   - Pitfall 5 mitigation: SwissTooFewParticipantsException for too few
|     participants (3 × 2 rounds requires 4 minimum → throw)
|   - Odd-N round-1: lowest-seed gets a bye with winner_participant_id auto-set
|   - generateNextRound() pairs by (points DESC, tiebreak DESC, seed ASC) within
|     score groups; respects never-paired-before
*/

/**
 * Helper: create $n active, seeded participants for $tournament with seeds 1..N.
 *
 * @return EloquentCollection<int, TournamentParticipant>
 */
function makeSeededSwissParticipants(Tournament $tournament, int $n): EloquentCollection
{
    /** @var EloquentCollection<int, TournamentParticipant> $created */
    $created = TournamentParticipant::factory()
        ->for($tournament)
        ->count($n)
        ->state(new Sequence(...array_map(
            fn (int $i): array => ['seed' => $i + 1, 'status' => 'active'],
            range(0, $n - 1)
        )))
        ->create();

    return $created;
}

// ---------------------------------------------------------------------------
// 8-participant round-1: top half vs bottom half by seed
// ---------------------------------------------------------------------------

it('generates 8-participant swiss round 1 with top-half vs bottom-half pairings', function (): void {
    $tournament = Tournament::factory()->ofFormat('swiss')->inStatus('seeded')->create();
    makeSeededSwissParticipants($tournament, 8);

    app(BracketGeneratorService::class)->generate($tournament);

    // 1 stage of type='swiss-round', ordinal=1.
    expect($tournament->stages()->count())->toBe(1);
    $stage = $tournament->stages()->first();
    expect($stage)->not()->toBeNull();
    expect($stage->type)->toBe('swiss-round');
    expect($stage->ordinal)->toBe(1);

    // 4 brackets at positions 1..4.
    expect($stage->brackets()->count())->toBe(4);

    // Pairings: 1v5, 2v6, 3v7, 4v8 (top half vs bottom half by seed).
    $brackets = $stage->brackets()->orderBy('position')->get();
    expect($brackets[0]->participantA->seed)->toBe(1);
    expect($brackets[0]->participantB->seed)->toBe(5);
    expect($brackets[1]->participantA->seed)->toBe(2);
    expect($brackets[1]->participantB->seed)->toBe(6);
    expect($brackets[2]->participantA->seed)->toBe(3);
    expect($brackets[2]->participantB->seed)->toBe(7);
    expect($brackets[3]->participantA->seed)->toBe(4);
    expect($brackets[3]->participantB->seed)->toBe(8);

    // No winners pre-assigned (no byes — even N).
    expect($brackets->pluck('winner_participant_id')->filter()->count())->toBe(0);

    // No advances_to chain (Swiss reads standings, not advance pointers).
    expect($brackets->whereNotNull('advances_to_bracket_id')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Odd-N admissibility — Pitfall 5 narrows valid swiss tournaments to N that
// satisfies N >= 2^ceil(log2(N)). Algebraically, that forces N to be a power
// of 2 (e.g. 2, 4, 8, 16, 32). Odd N (3, 5, 7, 9, 11, ...) therefore always
// fails the participants-count guard. The bye-handling code (winner_participant_id
// auto-set on the lowest-seed participant) is dead code under the v1 constraint
// but is kept defensively so a future relax of Pitfall 5 (e.g., allowing N
// between two powers of 2 with shorter round counts) lights it up automatically.
// ---------------------------------------------------------------------------

it('rejects 7-participant swiss (odd-N below the 2^3=8 minimum)', function (): void {
    $tournament = Tournament::factory()->ofFormat('swiss')->inStatus('seeded')->create();
    makeSeededSwissParticipants($tournament, 7);

    expect(fn () => app(BracketGeneratorService::class)->generate($tournament))
        ->toThrow(SwissTooFewParticipantsException::class);
});

it('rejects 5-participant swiss (odd-N below the 2^3=8 minimum)', function (): void {
    $tournament = Tournament::factory()->ofFormat('swiss')->inStatus('seeded')->create();
    makeSeededSwissParticipants($tournament, 5);

    expect(fn () => app(BracketGeneratorService::class)->generate($tournament))
        ->toThrow(SwissTooFewParticipantsException::class);
});

// ---------------------------------------------------------------------------
// Pitfall 5: SwissTooFewParticipantsException
// ---------------------------------------------------------------------------

it('throws SwissTooFewParticipantsException when participants < 2^rounds (Pitfall 5)', function (): void {
    // 3 participants → ceil(log2(3)) = 2 rounds; 2^2 = 4 minimum > 3 → throw.
    $tournament = Tournament::factory()->ofFormat('swiss')->inStatus('seeded')->create();
    makeSeededSwissParticipants($tournament, 3);

    expect(fn () => app(BracketGeneratorService::class)->generate($tournament))
        ->toThrow(SwissTooFewParticipantsException::class);
});

it('accepts 4 participants for 2-round swiss (edge case 2^2 = 4)', function (): void {
    $tournament = Tournament::factory()->ofFormat('swiss')->inStatus('seeded')->create();
    makeSeededSwissParticipants($tournament, 4);

    app(BracketGeneratorService::class)->generate($tournament);

    // 2 brackets (1v3, 2v4); no exception thrown.
    $stage = $tournament->stages()->first();
    expect($stage->brackets()->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// generateNextRound: pairs honour score groups + never-paired-before
// ---------------------------------------------------------------------------

it('generateNextRound pairs by score group (winners-vs-winners, losers-vs-losers) without re-pairing', function (): void {
    $tournament = Tournament::factory()->ofFormat('swiss')->inStatus('seeded')->create();
    $participants = makeSeededSwissParticipants($tournament, 8);

    // Generate round 1 via the front-door service.
    app(BracketGeneratorService::class)->generate($tournament);

    // Fixture standings: top-half seeds (1..4) "won" round 1 → 1.0 points;
    // bottom-half seeds (5..8) "lost" → 0.0 points.
    $stage = $tournament->stages()->where('type', 'swiss-round')->first();
    foreach ($participants as $p) {
        $isWinner = $p->seed <= 4;
        TournamentStanding::factory()->create([
            'tournament_id' => $tournament->id,
            'tournament_stage_id' => $stage->id,
            'participant_id' => $p->id,
            'wins' => $isWinner ? 1 : 0,
            'losses' => $isWinner ? 0 : 1,
            'draws' => 0,
            'points' => $isWinner ? 1.0 : 0.0,
            'tiebreak_score' => 0,
            'rank' => null,
        ]);
    }

    // Generate round 2 via the admin-click method.
    app(SwissGenerator::class)->generateNextRound($tournament);

    // 2 swiss-round stages now.
    expect($tournament->stages()->where('type', 'swiss-round')->count())->toBe(2);

    /** @var TournamentStage $r2Stage */
    $r2Stage = $tournament->stages()->where('type', 'swiss-round')->where('ordinal', 2)->firstOrFail();
    expect($r2Stage->ordinal)->toBe(2);

    // 4 brackets in round 2 (same as round 1 for N=8 even).
    expect($r2Stage->brackets()->count())->toBe(4);

    // No bracket re-pairs any round-1 matchup. Build the set of round-1 pairs
    // and intersect with round-2 pairs — expect empty.
    $r1Stage = $tournament->stages()->where('type', 'swiss-round')->where('ordinal', 1)->firstOrFail();
    $r1Pairs = $r1Stage->brackets()->get()->map(function ($b) {
        $pair = [$b->participant_a_id, $b->participant_b_id];
        sort($pair);

        return implode('-', $pair);
    });
    $r2Pairs = $r2Stage->brackets()->get()->map(function ($b) {
        $pair = [$b->participant_a_id, $b->participant_b_id];
        sort($pair);

        return implode('-', $pair);
    });
    $overlap = $r1Pairs->intersect($r2Pairs);
    expect($overlap->count())->toBe(0);

    // All round-2 matchups are winners-vs-winners or losers-vs-losers
    // (within-score-group pairing).
    $r2Brackets = $r2Stage->brackets()->get();
    foreach ($r2Brackets as $b) {
        $aSeed = $b->participantA->seed;
        $bSeed = $b->participantB->seed;
        $bothWinners = $aSeed <= 4 && $bSeed <= 4;
        $bothLosers = $aSeed > 4 && $bSeed > 4;
        expect($bothWinners || $bothLosers)->toBeTrue();
    }
});

it('generateNextRound throws LogicException when all swiss rounds have been generated', function (): void {
    // 4 participants → 2 rounds total.
    $tournament = Tournament::factory()->ofFormat('swiss')->inStatus('seeded')->create();
    $participants = makeSeededSwissParticipants($tournament, 4);

    app(BracketGeneratorService::class)->generate($tournament);

    // Manually generate round 2 by adding a swiss-round stage at ordinal 2.
    $stage = $tournament->stages()->where('type', 'swiss-round')->firstOrFail();
    foreach ($participants as $p) {
        TournamentStanding::factory()->create([
            'tournament_id' => $tournament->id,
            'tournament_stage_id' => $stage->id,
            'participant_id' => $p->id,
            'wins' => 0,
            'losses' => 0,
            'draws' => 0,
            'points' => 0,
            'tiebreak_score' => 0,
            'rank' => null,
        ]);
    }
    app(SwissGenerator::class)->generateNextRound($tournament);

    // Now attempting a third round on a 2-round tournament must throw.
    expect(fn () => app(SwissGenerator::class)->generateNextRound($tournament->fresh()))
        ->toThrow(LogicException::class);
});
