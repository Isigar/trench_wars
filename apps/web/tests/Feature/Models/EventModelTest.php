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
|
| OBSERVER-AWARE (plan 04-08): MatchObserver (registered on GameMatch::booted())
| auto-creates an Event row for every public, non-cancelled GameMatch save. To
| isolate the Event model's own contract from observer-driven writes, tests that
| need to manually call Event::factory()->create() on a specific match use
| is_public=false so the observer skips that match. Tests that exercise the
| polymorphic round-trip with an observer-created Event use the public default.
*/

it('creates a valid event via factory (standalone GameMatch owner with is_public=false)', function (): void {
    // Non-public match so the observer does NOT create a competing Event row.
    $match = GameMatch::factory()->create(['is_public' => false]);
    $event = Event::factory()->create([
        'eventable_type' => GameMatch::class,
        'eventable_id' => $match->id,
    ]);

    expect($event->exists)->toBeTrue();
    expect($event->is_public)->toBeTrue();
    expect($event->eventable_type)->toBe(GameMatch::class);
});

it('round-trips title through HasTranslations', function (): void {
    $match = GameMatch::factory()->create(['is_public' => false]);
    $event = Event::factory()->create([
        'eventable_type' => GameMatch::class,
        'eventable_id' => $match->id,
        'title' => ['en' => 'Original'],
    ]);

    $event->setTranslation('title', 'en', 'Friday Match');
    $event->save();

    expect($event->fresh()->getTranslation('title', 'en'))->toBe('Friday Match');
});

it('enforces events_one_per_owner UNIQUE on (eventable_type, eventable_id)', function (): void {
    // Public match → MatchObserver auto-creates Event #1 inside the save.
    $match = GameMatch::factory()->create(['is_public' => true]);
    expect(Event::where('eventable_id', $match->id)->count())->toBe(1);

    // A second manual Event row on the same owner violates events_one_per_owner.
    expect(fn () => Event::factory()->create([
        'eventable_type' => GameMatch::class,
        'eventable_id' => $match->id,
    ]))->toThrow(QueryException::class);
});

it('resolves morphTo eventable() to a GameMatch instance', function (): void {
    // Use the observer-created Event from a public match.
    $match = GameMatch::factory()->create(['is_public' => true]);
    $event = Event::where('eventable_type', GameMatch::class)
        ->where('eventable_id', $match->id)
        ->firstOrFail();

    $reloaded = $event->fresh();
    expect($reloaded->eventable)->toBeInstanceOf(GameMatch::class);
    expect($reloaded->eventable->is($match))->toBeTrue();
});

it('round-trips through morphOne back to the owning match', function (): void {
    $match = GameMatch::factory()->create(['is_public' => true]);
    $event = Event::where('eventable_type', GameMatch::class)
        ->where('eventable_id', $match->id)
        ->firstOrFail();

    $reloaded = $match->fresh()->load('event');
    expect($reloaded->event?->id)->toBe($event->id);
});

it('accepts a NULL ends_at (column is nullable)', function (): void {
    $match = GameMatch::factory()->create(['is_public' => false]);
    $event = Event::factory()->create([
        'eventable_type' => GameMatch::class,
        'eventable_id' => $match->id,
        'ends_at' => null,
    ]);
    expect($event->ends_at)->toBeNull();
});

it('logs activity on create (D-012)', function (): void {
    $match = GameMatch::factory()->create(['is_public' => false]);
    $event = Event::factory()->create([
        'eventable_type' => GameMatch::class,
        'eventable_id' => $match->id,
    ]);

    $activity = Activity::query()
        ->where('subject_type', Event::class)
        ->where('subject_id', $event->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});
