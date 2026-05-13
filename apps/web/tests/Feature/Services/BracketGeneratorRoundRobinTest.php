<?php

declare(strict_types=1);

use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Services\Brackets\BracketGeneratorService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Sequence;

/*
| Source: 06-07-PLAN.md Task 2 — replaces Wave 0 RED stub from 06-01.
|
| Covers RoundRobinGenerator via BracketGeneratorService dispatch:
|   - RESEARCH Pattern 4 circle method
|   - 4-participant happy path: 3 rounds × 2 matches = 6 brackets; every C(4,2)=6 pair plays once
|   - 5-participant odd-N case with ghost participant skipping
|   - advances_to_bracket_id is NULL for ALL round-robin brackets
|   - Stage type='group', ordinal=1
*/

/**
 * Helper: create $n active, seeded participants for $tournament with seeds 1..N.
 *
 * @return EloquentCollection<int, TournamentParticipant>
 */
function makeSeededRoundRobinParticipants(Tournament $tournament, int $n): EloquentCollection
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
// 4-participant happy path — even N, 3 rounds × 2 matches = 6 brackets
// ---------------------------------------------------------------------------

it('generates 4-participant round-robin with 3 rounds × 2 matches (6 brackets)', function (): void {
    $tournament = Tournament::factory()->ofFormat('round_robin')->inStatus('seeded')->create();
    makeSeededRoundRobinParticipants($tournament, 4);

    app(BracketGeneratorService::class)->generate($tournament);

    // 1 stage of type='group'.
    expect($tournament->stages()->count())->toBe(1);
    $stage = $tournament->stages()->first();
    expect($stage)->not()->toBeNull();
    expect($stage->type)->toBe('group');
    expect($stage->ordinal)->toBe(1);

    // 3 rounds × 2 matches each = 6 brackets.
    expect($stage->brackets()->count())->toBe(6);
    expect($stage->brackets()->where('round_number', 1)->count())->toBe(2);
    expect($stage->brackets()->where('round_number', 2)->count())->toBe(2);
    expect($stage->brackets()->where('round_number', 3)->count())->toBe(2);

    // Every C(4, 2) = 6 unique pair plays exactly once.
    $brackets = $stage->brackets()->get();
    $pairs = $brackets->map(function ($b) {
        $pair = [$b->participant_a_id, $b->participant_b_id];
        sort($pair);

        return implode('-', $pair);
    })->unique();
    expect($pairs->count())->toBe(6);
});

it('sets advances_to_bracket_id to NULL on every round-robin bracket', function (): void {
    $tournament = Tournament::factory()->ofFormat('round_robin')->inStatus('seeded')->create();
    makeSeededRoundRobinParticipants($tournament, 4);

    app(BracketGeneratorService::class)->generate($tournament);

    $brackets = $tournament->stages()->first()->brackets()->get();
    expect($brackets->whereNotNull('advances_to_bracket_id')->count())->toBe(0);
    expect($brackets->whereNotNull('loser_advances_to_bracket_id')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// 5-participant odd N — ghost participant skipping
// ---------------------------------------------------------------------------

it('generates 5-participant round-robin with ghost-pairings skipped (10 brackets, 10 unique pairs)', function (): void {
    $tournament = Tournament::factory()->ofFormat('round_robin')->inStatus('seeded')->create();
    makeSeededRoundRobinParticipants($tournament, 5);

    app(BracketGeneratorService::class)->generate($tournament);

    $stage = $tournament->stages()->first();
    expect($stage)->not()->toBeNull();
    expect($stage->type)->toBe('group');

    // N=5 → ghost makes 6; 5 rounds × 3 paired matches per round = 15 - 5 ghost
    // matches (1 per round) = 10 real brackets.
    $brackets = $stage->brackets()->get();
    expect($brackets->count())->toBe(10);

    // Total rounds = 5 (one bye-round per participant).
    expect($brackets->pluck('round_number')->unique()->count())->toBe(5);

    // Every C(5, 2) = 10 unique pair plays exactly once.
    $pairs = $brackets->map(function ($b) {
        $pair = [$b->participant_a_id, $b->participant_b_id];
        sort($pair);

        return implode('-', $pair);
    })->unique();
    expect($pairs->count())->toBe(10);

    // No null participant slots in any materialised bracket (ghost-vs-real
    // rows were skipped at insertion).
    expect($brackets->whereNull('participant_a_id')->count())->toBe(0);
    expect($brackets->whereNull('participant_b_id')->count())->toBe(0);
});

it('caps round-robin round count at N-1 for even N (2-participant edge case)', function (): void {
    $tournament = Tournament::factory()->ofFormat('round_robin')->inStatus('seeded')->create();
    makeSeededRoundRobinParticipants($tournament, 2);

    app(BracketGeneratorService::class)->generate($tournament);

    $stage = $tournament->stages()->first();
    expect($stage->brackets()->count())->toBe(1); // 1 round, 1 match
    expect($stage->brackets()->where('round_number', 1)->count())->toBe(1);
});

it('rejects round-robin generation when fewer than 2 active participants exist', function (): void {
    $tournament = Tournament::factory()->ofFormat('round_robin')->inStatus('seeded')->create();
    makeSeededRoundRobinParticipants($tournament, 1);

    expect(fn () => app(BracketGeneratorService::class)->generate($tournament))
        ->toThrow(InvalidArgumentException::class);
});
