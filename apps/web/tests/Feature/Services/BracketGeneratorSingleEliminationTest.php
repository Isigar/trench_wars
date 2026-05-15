<?php

declare(strict_types=1);

use App\Exceptions\BracketsAlreadyGeneratedException;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Services\Brackets\BracketGeneratorService;
use App\Services\Brackets\SingleEliminationGenerator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Sequence;
use InvalidArgumentException;
use ReflectionMethod;

/*
| Source: 06-06-PLAN.md Task 1 — replaces Wave 0 RED stub from 06-01.
|
| Covers SingleEliminationGenerator via BracketGeneratorService dispatch:
|   - RESEARCH Pattern 3 inner_outer ordering (sizes 4/8/16)
|   - Bye distribution to top seeds (N=5/6/7 not power-of-2)
|   - advances_to_bracket_id chain correctness (Pitfall 2 ceil() mitigation)
|   - Idempotency guard (Pitfall 3: BracketsAlreadyGeneratedException)
|   - Bye-winner propagation into round-2 participant slots
|   - recursive computeInnerOuter() matches hardcoded 32-element case
|
| NAMING NOTE (D-04-03-A): Match model class is GameMatch. No `match($x)`
| expressions appear here so the alias-on-import pattern is not needed.
*/

/**
 * Helper: create $n active, seeded participants for $tournament with seeds 1..N.
 *
 * @return EloquentCollection<int, TournamentParticipant>
 */
function makeSeededParticipants(Tournament $tournament, int $n): EloquentCollection
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
// 8-participant happy path — full inner_outer ordering verification
// ---------------------------------------------------------------------------

it('generates an 8-participant single-elim bracket with inner_outer seeding', function (): void {
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('seeded')->create();
    makeSeededParticipants($tournament, 8);

    app(BracketGeneratorService::class)->generate($tournament);

    // 1 stage; 7 brackets total (4 + 2 + 1).
    expect($tournament->stages()->count())->toBe(1);
    $stage = $tournament->stages()->first();
    expect($stage->brackets()->count())->toBe(7);
    expect($stage->brackets()->where('round_number', 1)->count())->toBe(4);
    expect($stage->brackets()->where('round_number', 2)->count())->toBe(2);
    expect($stage->brackets()->where('round_number', 3)->count())->toBe(1);

    // Inner_outer round-1 pairings (size=8 → [1,8,4,5,2,7,3,6]):
    //   p=1: 1 vs 8; p=2: 4 vs 5; p=3: 2 vs 7; p=4: 3 vs 6
    $round1 = $stage->brackets()->where('round_number', 1)->with(['participantA', 'participantB'])->orderBy('position')->get();
    expect($round1[0]->participantA->seed)->toBe(1);
    expect($round1[0]->participantB->seed)->toBe(8);
    expect($round1[1]->participantA->seed)->toBe(4);
    expect($round1[1]->participantB->seed)->toBe(5);
    expect($round1[2]->participantA->seed)->toBe(2);
    expect($round1[2]->participantB->seed)->toBe(7);
    expect($round1[3]->participantA->seed)->toBe(3);
    expect($round1[3]->participantB->seed)->toBe(6);

    // No winners pre-assigned (all 8 slots filled — no byes).
    expect($round1->pluck('winner_participant_id')->filter()->count())->toBe(0);
});

it('wires advances_to chain so round-1 positions 1+2 share a round-2 target', function (): void {
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('seeded')->create();
    makeSeededParticipants($tournament, 8);

    app(BracketGeneratorService::class)->generate($tournament);

    $round1 = $tournament->stages()->first()->brackets()->where('round_number', 1)->with(['participantA', 'participantB'])->orderBy('position')->get();

    // Pitfall 2 ceil() mitigation: ceil(1/2)=1, ceil(2/2)=1 → both feed semi position 1.
    //                              ceil(3/2)=2, ceil(4/2)=2 → both feed semi position 2.
    expect($round1[0]->advances_to_bracket_id)->toBe($round1[1]->advances_to_bracket_id);
    expect($round1[2]->advances_to_bracket_id)->toBe($round1[3]->advances_to_bracket_id);
    expect($round1[0]->advances_to_bracket_id)->not()->toBe($round1[2]->advances_to_bracket_id);

    // Round-2 final-advance: both semis feed into the same final (position 1).
    $round2 = $tournament->stages()->first()->brackets()->where('round_number', 2)->orderBy('position')->get();
    $round3 = $tournament->stages()->first()->brackets()->where('round_number', 3)->first();
    expect($round2[0]->advances_to_bracket_id)->toBe($round3->id);
    expect($round2[1]->advances_to_bracket_id)->toBe($round3->id);

    // Final has no advances_to (terminal bracket).
    expect($round3->advances_to_bracket_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// 4-participant happy path — minimum-size bracket
// ---------------------------------------------------------------------------

it('generates a 4-participant single-elim bracket (2 semis + 1 final)', function (): void {
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('seeded')->create();
    makeSeededParticipants($tournament, 4);

    app(BracketGeneratorService::class)->generate($tournament);

    $stage = $tournament->stages()->first();
    expect($stage->brackets()->count())->toBe(3); // 2 + 1

    // Inner_outer size=4 → [1,4,2,3]: p=1: 1 vs 4; p=2: 2 vs 3.
    $round1 = $stage->brackets()->where('round_number', 1)->with(['participantA', 'participantB'])->orderBy('position')->get();
    expect($round1[0]->participantA->seed)->toBe(1);
    expect($round1[0]->participantB->seed)->toBe(4);
    expect($round1[1]->participantA->seed)->toBe(2);
    expect($round1[1]->participantB->seed)->toBe(3);
});

// ---------------------------------------------------------------------------
// Bye distribution — N=7 (1 bye), N=6 (2 byes), N=5 (3 byes)
// ---------------------------------------------------------------------------

it('handles 7-participant single-elim with 1 bye awarded to top seed', function (): void {
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('seeded')->create();
    makeSeededParticipants($tournament, 7);

    app(BracketGeneratorService::class)->generate($tournament);

    // bracketSize=8, byeCount=1. Inner_outer pos 0+1 are seeds 1+8 → seed 8 is the
    // missing seed (no participant 8) so seed 1 plays a bye in round-1 position 1.
    $round1 = $tournament->stages()->first()->brackets()->where('round_number', 1)->with(['participantA', 'participantB'])->orderBy('position')->get();

    $byes = $round1->filter(fn ($b) => $b->participant_b_id === null || $b->participant_a_id === null);
    expect($byes->count())->toBe(1);

    /** @var TournamentBracket $byeBracket */
    $byeBracket = $byes->first();
    expect($byeBracket->participantA->seed)->toBe(1);
    expect($byeBracket->participantB)->toBeNull();
    expect($byeBracket->winner_participant_id)->not()->toBeNull();
    expect($byeBracket->winner_participant_id)->toBe($byeBracket->participant_a_id);
});

it('handles 6-participant single-elim with 2 byes awarded to top seeds', function (): void {
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('seeded')->create();
    makeSeededParticipants($tournament, 6);

    app(BracketGeneratorService::class)->generate($tournament);

    // bracketSize=8, byeCount=2. ordering=[1,8,4,5,2,7,3,6]; missing seeds=[7,8]
    // → byes land in round-1 positions where the B participant is seed 7 or 8.
    //   p=1: 1 vs 8 → seed 1 gets the bye (seed 8 absent).
    //   p=3: 2 vs 7 → seed 2 gets the bye (seed 7 absent).
    $round1 = $tournament->stages()->first()->brackets()->where('round_number', 1)->with(['participantA', 'participantB'])->orderBy('position')->get();
    $byeWinnerSeeds = $round1
        ->filter(fn ($b) => $b->winner_participant_id !== null)
        ->map(fn ($b) => $b->participantA->seed)
        ->values()
        ->all();

    expect($byeWinnerSeeds)->toHaveCount(2);
    sort($byeWinnerSeeds);
    expect($byeWinnerSeeds)->toBe([1, 2]);
});

it('handles 5-participant single-elim with 3 byes awarded to top seeds', function (): void {
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('seeded')->create();
    makeSeededParticipants($tournament, 5);

    app(BracketGeneratorService::class)->generate($tournament);

    // bracketSize=8, byeCount=3. ordering=[1,8,4,5,2,7,3,6]; missing seeds=[6,7,8]
    //   p=1: 1 vs 8 → seed 1 bye
    //   p=3: 2 vs 7 → seed 2 bye
    //   p=4: 3 vs 6 → seed 3 bye
    //   p=2: 4 vs 5 → real match (no bye)
    $round1 = $tournament->stages()->first()->brackets()->where('round_number', 1)->with(['participantA', 'participantB'])->orderBy('position')->get();
    $byeWinnerSeeds = $round1
        ->filter(fn ($b) => $b->winner_participant_id !== null)
        ->map(fn ($b) => $b->participantA->seed)
        ->values()
        ->all();

    expect($byeWinnerSeeds)->toHaveCount(3);
    sort($byeWinnerSeeds);
    expect($byeWinnerSeeds)->toBe([1, 2, 3]);

    // Confirm seeds 4+5 are the only round-1 played pairing.
    $playedBracket = $round1->first(fn ($b) => $b->winner_participant_id === null);
    expect($playedBracket->participantA->seed)->toBe(4);
    expect($playedBracket->participantB->seed)->toBe(5);
});

// ---------------------------------------------------------------------------
// Bye-winner propagation into round 2 participant slots
// ---------------------------------------------------------------------------

it('propagates round-1 bye winners into the correct round-2 participant slot', function (): void {
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('seeded')->create();
    makeSeededParticipants($tournament, 7);

    app(BracketGeneratorService::class)->generate($tournament);

    $stage = $tournament->stages()->first();
    $round1 = $stage->brackets()->where('round_number', 1)->with(['participantA', 'participantB'])->orderBy('position')->get();

    // Seed 1's bye is at p=1 (odd) → participant_a_id of round-2 position 1.
    /** @var TournamentBracket $byeR1 */
    $byeR1 = $round1->first(fn ($b) => $b->winner_participant_id !== null);
    $round2Target = $stage->brackets()->where('id', $byeR1->advances_to_bracket_id)->first();

    expect($round2Target->participant_a_id)->toBe($byeR1->winner_participant_id);
});

// ---------------------------------------------------------------------------
// Idempotency guard (Pitfall 3)
// ---------------------------------------------------------------------------

it('throws BracketsAlreadyGeneratedException on second generate() call', function (): void {
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('seeded')->create();
    makeSeededParticipants($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);

    expect(fn () => app(BracketGeneratorService::class)->generate($tournament->fresh()))
        ->toThrow(BracketsAlreadyGeneratedException::class);
});

it('rejects bracket generation when fewer than 2 active participants exist', function (): void {
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('seeded')->create();
    makeSeededParticipants($tournament, 1);

    expect(fn () => app(BracketGeneratorService::class)->generate($tournament))
        ->toThrow(InvalidArgumentException::class);
});

// ---------------------------------------------------------------------------
// 16-participant verification — full second inner_outer level
// ---------------------------------------------------------------------------

it('generates a 16-participant single-elim with 15 total brackets (8+4+2+1)', function (): void {
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('seeded')->create();
    makeSeededParticipants($tournament, 16);

    app(BracketGeneratorService::class)->generate($tournament);

    $stage = $tournament->stages()->first();
    expect($stage->brackets()->count())->toBe(15);
    expect($stage->brackets()->where('round_number', 1)->count())->toBe(8);
    expect($stage->brackets()->where('round_number', 2)->count())->toBe(4);
    expect($stage->brackets()->where('round_number', 3)->count())->toBe(2);
    expect($stage->brackets()->where('round_number', 4)->count())->toBe(1);

    // First and last round-1 pairings against the hardcoded ordering
    // [1,16,8,9,4,13,5,12,2,15,7,10,3,14,6,11]:
    //   p=1: 1 vs 16; p=8: 6 vs 11.
    $round1 = $stage->brackets()->where('round_number', 1)->with(['participantA', 'participantB'])->orderBy('position')->get();
    expect($round1[0]->participantA->seed)->toBe(1);
    expect($round1[0]->participantB->seed)->toBe(16);
    expect($round1[7]->participantA->seed)->toBe(6);
    expect($round1[7]->participantB->seed)->toBe(11);
});

// ---------------------------------------------------------------------------
// computeInnerOuter() recursive correctness — must match hardcoded size=32
// ---------------------------------------------------------------------------

it('recursive computeInnerOuter() reproduces the hardcoded 32-element ordering', function (): void {
    $generator = app(SingleEliminationGenerator::class);
    $reflection = new ReflectionMethod($generator, 'computeInnerOuter');
    $reflection->setAccessible(true);

    $computed = $reflection->invoke($generator, 32);

    expect($computed)->toBe([
        1, 32, 16, 17, 8, 25, 9, 24, 4, 29, 13, 20, 5, 28, 12, 21,
        2, 31, 15, 18, 7, 26, 10, 23, 3, 30, 14, 19, 6, 27, 11, 22,
    ]);
});
