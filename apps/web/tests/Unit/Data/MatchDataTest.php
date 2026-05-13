<?php

declare(strict_types=1);

/*
| Wave 4 implementation — replaces Wave 0 RED stub from plan 04-01.
| Covers REQ-goal-match-workflows: MatchData fromModel preserves the full
| translatable JSONB locale array (Phase 3 Pitfall 4 — getTranslations() not
| the active-locale scalar) AND emits scheduled_at as ISO-8601 string.
| See .planning/phases/04-matches-manual/04-07-PLAN.md task 3.
*/

use App\Data\MatchData;
use App\Models\GameMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

uses(RefreshDatabase::class);

// --------------------------------------------------------------------------
// Pitfall 4: fromModel surfaces the full JSONB locale array, NOT the
// active-locale scalar.
// --------------------------------------------------------------------------

it('hydrates MatchData from a GameMatch model with translatable title', function (): void {
    $match = GameMatch::factory()->create();
    $match->setTranslation('title', 'en', 'Friday Night Skirmish');
    $match->setTranslation('title', 'fr', 'Escarmouche du Vendredi Soir');
    $match->save();

    $dto = MatchData::fromModel($match->fresh());

    expect($dto->title)->toBe([
        'en' => 'Friday Night Skirmish',
        'fr' => 'Escarmouche du Vendredi Soir',
    ]);
});

// --------------------------------------------------------------------------
// Null-coalesce: empty JSONB array collapses to null (Vue v-if absent contract).
// --------------------------------------------------------------------------

it('returns null title when match.title JSONB is empty array', function (): void {
    $match = GameMatch::factory()->create(['title' => []]);

    $dto = MatchData::fromModel($match->fresh());

    expect($dto->title)->toBeNull();
});

it('returns null description when match.description is null at the column', function (): void {
    $match = GameMatch::factory()->create(['title' => ['en' => 'No-Desc Match']]);

    $dto = MatchData::fromModel($match->fresh());

    expect($dto->description)->toBeNull();
});

// --------------------------------------------------------------------------
// ISO-8601 string emission for scheduled_at.
// --------------------------------------------------------------------------

it('emits scheduled_at as ISO-8601 string', function (): void {
    $match = GameMatch::factory()->create([
        'scheduled_at' => '2026-06-15 20:00:00',
    ]);

    $dto = MatchData::fromModel($match->fresh());

    expect($dto->scheduled_at)
        ->toBeString()
        ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});

// --------------------------------------------------------------------------
// Nullable FK fidelity — host_clan_id stays null.
// --------------------------------------------------------------------------

it('preserves host_clan_id null when match has no host clan', function (): void {
    $match = GameMatch::factory()->create(['host_clan_id' => null]);

    $dto = MatchData::fromModel($match->fresh());

    expect($dto->host_clan_id)->toBeNull();
});

it('preserves server_address null in default fixture (admin-only field stays on MatchData)', function (): void {
    $match = GameMatch::factory()->create();

    $dto = MatchData::fromModel($match->fresh());

    expect($dto->server_address)->toBeNull()
        ->and($dto->organiser_user_id)->toBe($match->organiser_user_id);
});

// --------------------------------------------------------------------------
// #[TypeScript] attribute is present — drives typescript-transformer output.
// --------------------------------------------------------------------------

it('emits #[TypeScript] attribute resolved by transformer reflection', function (): void {
    $attributes = (new ReflectionClass(MatchData::class))->getAttributes(TypeScript::class);

    expect($attributes)->not->toBeEmpty();
});
