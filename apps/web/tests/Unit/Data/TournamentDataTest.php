<?php

declare(strict_types=1);

/*
| Wave 5 GREEN — replaces Wave 0 RED stub from plan 06-01.
| Source: .planning/phases/06-tournaments-brackets/06-10-PLAN.md Task 1.
|
| Covers: TournamentData::fromModel happy path + translatable JSONB shape +
| #[TypeScript] attribute presence + relationLoaded() guard for stages /
| participants (Phase 3 Pitfall 4 mitigation).
*/

use App\Data\TournamentData;
use App\Models\Clan;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

uses(RefreshDatabase::class);

it('hydrates TournamentData from a Tournament model with translatable title', function (): void {
    $tournament = Tournament::factory()->create();
    $tournament->setTranslation('title', 'en', 'Spring Championship');
    $tournament->setTranslation('title', 'fr', 'Championnat de Printemps');
    $tournament->save();

    $fresh = $tournament->fresh();
    assert($fresh !== null);
    $dto = TournamentData::fromModel($fresh);

    expect($dto->title)->toBe([
        'en' => 'Spring Championship',
        'fr' => 'Championnat de Printemps',
    ])
        ->and($dto->id)->toBe($tournament->id)
        ->and($dto->slug)->toBe($tournament->slug)
        ->and($dto->format)->toBe($tournament->format)
        ->and($dto->status)->toBe('draft')
        ->and($dto->is_public)->toBeTrue();
});

it('returns null title when tournament.title JSONB is empty array', function (): void {
    $tournament = Tournament::factory()->create(['title' => []]);

    $fresh = $tournament->fresh();
    assert($fresh !== null);
    $dto = TournamentData::fromModel($fresh);

    expect($dto->title)->toBeNull();
});

it('emits starts_at as ISO-8601 string and ends_at as null in default fixture', function (): void {
    $tournament = Tournament::factory()->create([
        'starts_at' => '2026-06-15 20:00:00',
        'ends_at' => null,
    ]);

    $fresh = $tournament->fresh();
    assert($fresh !== null);
    $dto = TournamentData::fromModel($fresh);

    expect($dto->starts_at)
        ->toBeString()
        ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/')
        ->and($dto->ends_at)->toBeNull();
});

it('emits null participants and stages when relations are not eager-loaded (Pitfall 4 guard)', function (): void {
    $tournament = Tournament::factory()->create();

    $fresh = $tournament->fresh();
    assert($fresh !== null);
    $dto = TournamentData::fromModel($fresh);

    expect($dto->participants)->toBeNull()
        ->and($dto->stages)->toBeNull();
});

it('hydrates participants[] when participants relation is eager-loaded', function (): void {
    $tournament = Tournament::factory()->create();
    $clan = Clan::factory()->create();
    TournamentParticipant::factory()
        ->for($tournament)
        ->for($clan)
        ->create(['seed' => 1, 'status' => 'active']);

    $dto = TournamentData::fromModel($tournament->load('participants.clan'));

    expect($dto->participants)->toBeArray()
        ->and($dto->participants)->toHaveCount(1);
    assert($dto->participants !== null);
    expect($dto->participants[0]->seed)->toBe(1)
        ->and($dto->participants[0]->status)->toBe('active');
});

it('hydrates stages[] when stages relation is eager-loaded', function (): void {
    $tournament = Tournament::factory()->create();
    TournamentStage::factory()->for($tournament)->create(['type' => 'elim', 'ordinal' => 1]);

    $dto = TournamentData::fromModel($tournament->load('stages'));

    expect($dto->stages)->toBeArray()
        ->and($dto->stages)->toHaveCount(1);
    assert($dto->stages !== null);
    expect($dto->stages[0]->type)->toBe('elim');
});

it('emits #[TypeScript] attribute resolved by transformer reflection', function (): void {
    $attributes = (new ReflectionClass(TournamentData::class))->getAttributes(TypeScript::class);

    expect($attributes)->not->toBeEmpty();
});
