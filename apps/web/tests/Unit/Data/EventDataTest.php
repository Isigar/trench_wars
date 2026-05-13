<?php

declare(strict_types=1);

/*
| Wave 4 implementation — replaces Wave 0 RED stub from plan 04-01.
| Covers REQ-goal-match-workflows: EventData hydrates the polymorphic calendar
| projection (eventable_type FQN + eventable_id) and surfaces the translatable
| JSONB title via getTranslations() (Phase 3 Pitfall 4 + RESEARCH Pattern 8).
| See .planning/phases/04-matches-manual/04-07-PLAN.md task 3.
*/

use App\Data\EventData;
use App\Models\Event;
use App\Models\GameMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

uses(RefreshDatabase::class);

// --------------------------------------------------------------------------
// Polymorphic round-trip — eventable_type FQN string preserved verbatim.
// --------------------------------------------------------------------------

it('hydrates EventData from polymorphic match-owned Event', function (): void {
    $match = GameMatch::factory()->create();
    $event = Event::factory()->create([
        'eventable_type' => GameMatch::class,
        'eventable_id' => $match->id,
        'title' => ['en' => 'Friday Night Skirmish'],
    ]);

    $dto = EventData::fromModel($event->fresh());

    expect($dto->id)->toBe($event->id)
        ->and($dto->title)->toBe(['en' => 'Friday Night Skirmish']);
});

it('preserves eventable_type and eventable_id verbatim from model', function (): void {
    $match = GameMatch::factory()->create();
    $event = Event::factory()->create([
        'eventable_type' => GameMatch::class,
        'eventable_id' => $match->id,
    ]);

    $dto = EventData::fromModel($event->fresh());

    // FQN string — Vue components compare by 'App\\Models\\GameMatch'.
    expect($dto->eventable_type)->toBe(GameMatch::class)
        ->and($dto->eventable_type)->toBe('App\\Models\\GameMatch')
        ->and($dto->eventable_id)->toBe($match->id);
});

// --------------------------------------------------------------------------
// Translatable null-coalesce — empty JSONB collapses to null.
// --------------------------------------------------------------------------

it('returns null title when event.title JSONB is empty array', function (): void {
    $event = Event::factory()->create(['title' => []]);

    $dto = EventData::fromModel($event->fresh());

    expect($dto->title)->toBeNull();
});

// --------------------------------------------------------------------------
// Datetime emission — starts_at always present, ends_at nullable.
// --------------------------------------------------------------------------

it('emits starts_at and ends_at as ISO-8601 strings; ends_at nullable', function (): void {
    $event = Event::factory()->create([
        'starts_at' => '2026-06-15 20:00:00',
        'ends_at' => '2026-06-15 22:00:00',
    ]);

    $dto = EventData::fromModel($event->fresh());

    expect($dto->starts_at)
        ->toBeString()
        ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
    expect($dto->ends_at)
        ->toBeString()
        ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});

it('emits ends_at as null when event has no end timestamp', function (): void {
    $event = Event::factory()->create(['ends_at' => null]);

    $dto = EventData::fromModel($event->fresh());

    expect($dto->ends_at)->toBeNull();
});

// --------------------------------------------------------------------------
// #[TypeScript] attribute reflection — transformer trigger.
// --------------------------------------------------------------------------

it('emits #[TypeScript] attribute resolved by transformer reflection', function (): void {
    $attributes = (new ReflectionClass(EventData::class))->getAttributes(TypeScript::class);

    expect($attributes)->not->toBeEmpty();
});
