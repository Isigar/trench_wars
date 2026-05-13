<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\GameMatch;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/04-matches-manual/04-03-PLAN.md task 3.
| Replaces the Wave 0 RED stub from plan 04-01 (Wave 0 marker removed).
| Event is the polymorphic calendar projection (Pattern 8); MatchObserver upserts
| onto it in plan 04-08 — this file proves the model contract those writes depend on.
*/

it('creates a valid event via factory (standalone GameMatch owner)', function (): void {
    $event = Event::factory()->create();
    expect($event->exists)->toBeTrue();
    expect($event->is_public)->toBeTrue();
    expect($event->eventable_type)->toBe(GameMatch::class);
});

it('round-trips title through HasTranslations', function (): void {
    $event = Event::factory()->create(['title' => ['en' => 'Original']]);

    $event->setTranslation('title', 'en', 'Friday Match');
    $event->save();

    expect($event->fresh()->getTranslation('title', 'en'))->toBe('Friday Match');
});

it('enforces events_one_per_owner UNIQUE on (eventable_type, eventable_id)', function (): void {
    $match = GameMatch::factory()->create();
    Event::factory()->create([
        'eventable_type' => GameMatch::class,
        'eventable_id' => $match->id,
    ]);

    expect(fn () => Event::factory()->create([
        'eventable_type' => GameMatch::class,
        'eventable_id' => $match->id,
    ]))->toThrow(QueryException::class);
});

it('resolves morphTo eventable() to a GameMatch instance', function (): void {
    $match = GameMatch::factory()->create();
    $event = Event::factory()->create([
        'eventable_type' => GameMatch::class,
        'eventable_id' => $match->id,
    ]);

    $reloaded = $event->fresh();
    expect($reloaded->eventable)->toBeInstanceOf(GameMatch::class);
    expect($reloaded->eventable->is($match))->toBeTrue();
});

it('round-trips through morphOne back to the owning match', function (): void {
    $match = GameMatch::factory()->create();
    $event = Event::factory()->create([
        'eventable_type' => GameMatch::class,
        'eventable_id' => $match->id,
    ]);

    $reloaded = $match->fresh()->load('event');
    expect($reloaded->event?->id)->toBe($event->id);
});

it('accepts a NULL ends_at (column is nullable)', function (): void {
    $event = Event::factory()->create(['ends_at' => null]);
    expect($event->ends_at)->toBeNull();
});

it('logs activity on create (D-012)', function (): void {
    $event = Event::factory()->create();

    $activity = Activity::query()
        ->where('subject_type', Event::class)
        ->where('subject_id', $event->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});
