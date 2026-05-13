<?php

declare(strict_types=1);

/*
| Wave 5 GREEN — replaces Wave 0 RED stub from plan 06-01.
| Source: .planning/phases/06-tournaments-brackets/06-10-PLAN.md Task 1.
|
| Covers PublicTournamentData::fromModel:
|   - nodes[] composition: 1 BracketNodeData per TournamentBracket across stages
|   - winner edges[] composition: 1 per non-null advances_to_bracket_id
|   - loser edges[] composition: 1 per non-null loser_advances_to_bracket_id
|   - 8-clan single-elim: 7 nodes + 6 winner edges + 0 loser edges
|   - etag deterministic across identical-state calls
|   - participant_count excludes 'registered' (A5 LOCKED — D-06-09-E retention)
|   - #[TypeScript] attribute emission
*/

use App\Data\PublicTournamentData;
use App\Models\Clan;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use App\Services\Brackets\BracketGeneratorService;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

uses(RefreshDatabase::class);

/**
 * Build $n active, seeded participants for $tournament with seeds 1..N.
 */
function buildSeededParticipantsForPublic(Tournament $tournament, int $n): void
{
    TournamentParticipant::factory()
        ->for($tournament)
        ->count($n)
        ->state(new Sequence(...array_map(
            fn (int $i): array => ['seed' => $i + 1, 'status' => 'active'],
            range(0, $n - 1)
        )))
        ->create();
}

// ---------------------------------------------------------------------------
// nodes + edges composition (8-clan single-elim)
// ---------------------------------------------------------------------------

it('composes 7 nodes + 6 winner edges + 0 loser edges for an 8-clan single-elim', function (): void {
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('seeded')->create();
    buildSeededParticipantsForPublic($tournament, 8);

    app(BracketGeneratorService::class)->generate($tournament);

    $fresh = $tournament->fresh();
    assert($fresh !== null);
    $dto = PublicTournamentData::fromModel($fresh->load([
        'stages.brackets.participantA.clan',
        'stages.brackets.participantB.clan',
        'stages.brackets.match',
        'participants.clan',
    ]));

    expect($dto->nodes)->toHaveCount(7);
    $winnerEdges = array_filter($dto->edges, fn ($e) => $e->type === 'winner');
    $loserEdges = array_filter($dto->edges, fn ($e) => $e->type === 'loser');
    expect($winnerEdges)->toHaveCount(6)
        ->and($loserEdges)->toHaveCount(0);
});

it('assigns to_slot a for odd source positions and b for even source positions', function (): void {
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('seeded')->create();
    buildSeededParticipantsForPublic($tournament, 4);

    app(BracketGeneratorService::class)->generate($tournament);

    $fresh = $tournament->fresh();
    assert($fresh !== null);
    $dto = PublicTournamentData::fromModel($fresh->load([
        'stages.brackets.participantA.clan',
        'stages.brackets.participantB.clan',
        'stages.brackets.match',
        'participants.clan',
    ]));

    // 4-clan single-elim: 2 round-1 brackets (positions 1, 2) both advance to
    // the final (position 1). Position 1 → slot 'a'; position 2 → slot 'b'.
    expect($dto->edges)->toHaveCount(2);
    $slotsByPosition = [];
    foreach ($dto->edges as $edge) {
        // Find the source bracket's position via nodes[]
        $sourceNode = array_filter($dto->nodes, fn ($n) => $n->id === $edge->from_bracket_id);
        $sourceNode = array_values($sourceNode)[0];
        $slotsByPosition[$sourceNode->position] = $edge->to_slot;
    }
    expect($slotsByPosition[1])->toBe('a')
        ->and($slotsByPosition[2])->toBe('b');
});

// ---------------------------------------------------------------------------
// Loser edges — double-elim drop chain
// ---------------------------------------------------------------------------

it('emits a loser-type edge for each non-null loser_advances_to_bracket_id pointer', function (): void {
    $tournament = Tournament::factory()->ofFormat('double_elimination')->inStatus('seeded')->create();
    $stage = TournamentStage::factory()->for($tournament)->create(['type' => 'winners-bracket', 'ordinal' => 1]);
    $losersStage = TournamentStage::factory()->for($tournament)->create(['type' => 'losers-bracket', 'ordinal' => 2]);

    $loserSink = TournamentBracket::factory()
        ->for($losersStage, 'stage')
        ->create(['round_number' => 1, 'position' => 1]);

    TournamentBracket::factory()
        ->for($stage, 'stage')
        ->create([
            'round_number' => 1,
            'position' => 1,
            'loser_advances_to_bracket_id' => $loserSink->id,
        ]);

    $fresh = $tournament->fresh();
    assert($fresh !== null);
    $dto = PublicTournamentData::fromModel($fresh->load([
        'stages.brackets.participantA.clan',
        'stages.brackets.participantB.clan',
        'stages.brackets.match',
        'participants.clan',
    ]));

    $loserEdges = array_values(array_filter($dto->edges, fn ($e) => $e->type === 'loser'));
    expect($loserEdges)->toHaveCount(1)
        ->and($loserEdges[0]->to_bracket_id)->toBe($loserSink->id);
});

// ---------------------------------------------------------------------------
// etag determinism
// ---------------------------------------------------------------------------

it('produces a deterministic sha1 etag across identical-state calls', function (): void {
    $tournament = Tournament::factory()->ofFormat('single_elimination')->inStatus('seeded')->create();
    buildSeededParticipantsForPublic($tournament, 4);
    app(BracketGeneratorService::class)->generate($tournament);

    $loadRelations = [
        'stages.brackets.participantA.clan',
        'stages.brackets.participantB.clan',
        'stages.brackets.match',
        'participants.clan',
    ];

    $firstFresh = $tournament->fresh();
    $secondFresh = $tournament->fresh();
    assert($firstFresh !== null && $secondFresh !== null);
    $first = PublicTournamentData::fromModel($firstFresh->load($loadRelations));
    $second = PublicTournamentData::fromModel($secondFresh->load($loadRelations));

    expect($first->etag)
        ->toBeString()
        ->toMatch('/^[0-9a-f]{40}$/')
        ->toBe($second->etag);
});

// ---------------------------------------------------------------------------
// participant_count A5 LOCKED — withdrawn retained, registered excluded
// ---------------------------------------------------------------------------

it('participant_count excludes registered participants but retains active + withdrawn (A5 LOCKED)', function (): void {
    $tournament = Tournament::factory()->ofFormat('round_robin')->create();
    $clanA = Clan::factory()->create();
    $clanB = Clan::factory()->create();
    $clanC = Clan::factory()->create();
    $clanD = Clan::factory()->create();
    TournamentParticipant::factory()->for($tournament)->for($clanA)->create(['status' => 'active']);
    TournamentParticipant::factory()->for($tournament)->for($clanB)->create(['status' => 'withdrawn']);
    TournamentParticipant::factory()->for($tournament)->for($clanC)->create(['status' => 'registered']);
    TournamentParticipant::factory()->for($tournament)->for($clanD)->create(['status' => 'disqualified']);

    $fresh = $tournament->fresh();
    assert($fresh !== null);
    $dto = PublicTournamentData::fromModel($fresh->load([
        'stages.brackets.participantA.clan',
        'stages.brackets.participantB.clan',
        'stages.brackets.match',
        'participants.clan',
    ]));

    expect($dto->participant_count)->toBe(3); // active + withdrawn + disqualified
});

// ---------------------------------------------------------------------------
// Privacy gate placeholder (Phase 9 — clan names are public per D-018)
// ---------------------------------------------------------------------------

it('preserves public clan names on participants (D-018 — clan names always public)', function (): void {
    $tournament = Tournament::factory()->create();
    $clan = Clan::factory()->create(['name' => 'Public Clan']);
    TournamentParticipant::factory()
        ->for($tournament)
        ->for($clan)
        ->create(['seed' => 1, 'status' => 'active']);

    $fresh = $tournament->fresh();
    assert($fresh !== null);
    $dto = PublicTournamentData::fromModel($fresh->load([
        'stages.brackets.participantA.clan',
        'stages.brackets.participantB.clan',
        'stages.brackets.match',
        'participants.clan',
    ]));

    expect($dto->participants)->toBeArray();
    assert($dto->participants !== null);
    expect($dto->participants[0]->clan_name)->toBe('Public Clan');
});

it('emits #[TypeScript] attribute resolved by transformer reflection', function (): void {
    $attributes = (new ReflectionClass(PublicTournamentData::class))->getAttributes(TypeScript::class);

    expect($attributes)->not->toBeEmpty();
});
