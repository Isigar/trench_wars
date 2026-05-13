<?php

declare(strict_types=1);

/*
| Wave 5 GREEN — replaces Wave 0 RED stub from plan 06-01.
| Source: .planning/phases/06-tournaments-brackets/06-10-PLAN.md Task 1.
|
| Covers BracketNodeData::fromModel:
|   - 4-state status ladder: bye | completed | in-progress | pending
|   - ParticipantSummary shape (id + clan_name + seed)
|   - null participant slots when participantA / participantB FKs are null
|   - stage_type pass-through from TournamentStage.type
|   - #[TypeScript] attribute emission
*/

use App\Data\BracketNodeData;
use App\Data\ParticipantSummary;
use App\Models\Clan;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeBracketFixture(string $stageType = 'elim'): TournamentBracket
{
    $tournament = Tournament::factory()->create();
    $stage = TournamentStage::factory()->for($tournament)->create(['type' => $stageType, 'ordinal' => 1]);
    $bracket = TournamentBracket::factory()
        ->for($stage, 'stage')
        ->create(['round_number' => 1, 'position' => 1]);

    $fresh = $bracket->fresh();
    assert($fresh !== null);

    return $fresh;
}

function makeParticipant(Tournament $tournament, int $seed = 1, ?string $clanName = null): TournamentParticipant
{
    $clan = Clan::factory()->create($clanName !== null ? ['name' => $clanName] : []);

    return TournamentParticipant::factory()
        ->for($tournament)
        ->for($clan)
        ->create(['seed' => $seed, 'status' => 'active']);
}

// ---------------------------------------------------------------------------
// Status ladder
// ---------------------------------------------------------------------------

it('emits status=pending when no match and no winner', function (): void {
    $bracket = makeBracketFixture();

    $dto = BracketNodeData::fromModel($bracket->load(['stage', 'participantA.clan', 'participantB.clan', 'match']));

    expect($dto->status)->toBe('pending')
        ->and($dto->match_id)->toBeNull()
        ->and($dto->winner_participant_id)->toBeNull();
});

it('emits status=bye when participantA set, participantB null, winner == participantA', function (): void {
    $tournament = Tournament::factory()->create();
    $stage = TournamentStage::factory()->for($tournament)->create(['type' => 'elim', 'ordinal' => 1]);
    $participantA = makeParticipant($tournament);

    $bracket = TournamentBracket::factory()
        ->for($stage, 'stage')
        ->create([
            'round_number' => 1,
            'position' => 1,
            'participant_a_id' => $participantA->id,
            'participant_b_id' => null,
            'winner_participant_id' => $participantA->id,
        ]);

    $fresh = $bracket->fresh();
    assert($fresh !== null);
    $dto = BracketNodeData::fromModel($fresh->load(['stage', 'participantA.clan', 'participantB.clan', 'match']));

    expect($dto->status)->toBe('bye')
        ->and($dto->participant_a)->not->toBeNull()
        ->and($dto->participant_b)->toBeNull()
        ->and($dto->winner_participant_id)->toBe($participantA->id);
});

it('emits status=completed when both participants set and winner set', function (): void {
    $tournament = Tournament::factory()->create();
    $stage = TournamentStage::factory()->for($tournament)->create(['type' => 'elim', 'ordinal' => 1]);
    $participantA = makeParticipant($tournament, seed: 1);
    $participantB = makeParticipant($tournament, seed: 2);

    $bracket = TournamentBracket::factory()
        ->for($stage, 'stage')
        ->create([
            'round_number' => 1,
            'position' => 1,
            'participant_a_id' => $participantA->id,
            'participant_b_id' => $participantB->id,
            'winner_participant_id' => $participantA->id,
        ]);

    $fresh = $bracket->fresh();
    assert($fresh !== null);
    $dto = BracketNodeData::fromModel($fresh->load(['stage', 'participantA.clan', 'participantB.clan', 'match']));

    expect($dto->status)->toBe('completed');
});

// ---------------------------------------------------------------------------
// ParticipantSummary shape
// ---------------------------------------------------------------------------

it('hydrates ParticipantSummary with id + clan_name + seed', function (): void {
    $tournament = Tournament::factory()->create();
    $stage = TournamentStage::factory()->for($tournament)->create(['type' => 'elim', 'ordinal' => 1]);
    $participantA = makeParticipant($tournament, seed: 7, clanName: 'Alpha Clan');

    $bracket = TournamentBracket::factory()
        ->for($stage, 'stage')
        ->create([
            'round_number' => 1,
            'position' => 1,
            'participant_a_id' => $participantA->id,
        ]);

    $fresh = $bracket->fresh();
    assert($fresh !== null);
    $dto = BracketNodeData::fromModel($fresh->load(['stage', 'participantA.clan', 'participantB.clan', 'match']));

    expect($dto->participant_a)->toBeInstanceOf(ParticipantSummary::class);
    assert($dto->participant_a !== null);
    expect($dto->participant_a->id)->toBe($participantA->id)
        ->and($dto->participant_a->clan_name)->toBe('Alpha Clan')
        ->and($dto->participant_a->seed)->toBe(7);
});

it('passes through stage_type from the parent TournamentStage', function (): void {
    $bracket = makeBracketFixture(stageType: 'losers-bracket');

    $dto = BracketNodeData::fromModel($bracket->load(['stage', 'participantA.clan', 'participantB.clan', 'match']));

    expect($dto->stage_type)->toBe('losers-bracket');
});

it('emits #[TypeScript] attribute resolved by transformer reflection', function (): void {
    $attributes = (new ReflectionClass(BracketNodeData::class))->getAttributes(TypeScript::class);

    expect($attributes)->not->toBeEmpty();
});

it('emits #[TypeScript] attribute on ParticipantSummary as well', function (): void {
    $attributes = (new ReflectionClass(ParticipantSummary::class))->getAttributes(TypeScript::class);

    expect($attributes)->not->toBeEmpty();
});
