<?php

declare(strict_types=1);

/*
| Source: .planning/phases/04-matches-manual/04-08-PLAN.md task 2 +
|         04-RESEARCH.md § Pattern 8 (polymorphic Event sync).
| Replaces the Wave 0 RED stub from plan 04-01 (Wave 0 marker removed).
|
| SC-5 (second half): "creating a public match auto-creates a kept-in-sync Event
| row" — proven end-to-end via 8 it() blocks covering the observer's full saved()
| + deleted() invariants:
|
|   1. public-on-create → Event created
|   2. private-on-create → NO Event created
|   3. is_public=true → is_public=false → Event deleted
|   4. status=cancelled → Event deleted (even with is_public=true)
|   5. match.title edit → Event.title overwritten by saved() listener
|   6. match.scheduled_at edit → Event.starts_at overwritten
|   7. match deleted → Event hard-deleted via deleted() listener
|   8. is_public flipped false→true → Event re-created (updateOrCreate idempotent)
|
| Pitfall 12: all transitions use $match->update() / $match->save() — never
| GameMatch::query()->update() which bypasses model events. The observer is
| fired only by single-record Eloquent operations.
*/

use App\Models\Event;
use App\Models\GameMatch;

it('creates an Event row when a public match is saved', function (): void {
    $match = GameMatch::factory()->create(['is_public' => true, 'status' => 'open']);

    $event = Event::where('eventable_type', GameMatch::class)
        ->where('eventable_id', $match->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->is_public)->toBeTrue()
        ->and($event->eventable_type)->toBe(GameMatch::class)
        ->and($event->eventable_id)->toBe($match->id)
        ->and($event->starts_at->equalTo($match->scheduled_at))->toBeTrue();
});

it('does NOT create an Event row when a draft is_public=false match is saved', function (): void {
    GameMatch::factory()->create(['is_public' => false, 'status' => 'draft']);

    expect(Event::count())->toBe(0);
});

it('deletes the Event when match.is_public is flipped to false', function (): void {
    $match = GameMatch::factory()->create(['is_public' => true, 'status' => 'open']);
    expect(Event::where('eventable_type', GameMatch::class)
        ->where('eventable_id', $match->id)->count())->toBe(1);

    $match->update(['is_public' => false]);

    expect(Event::where('eventable_type', GameMatch::class)
        ->where('eventable_id', $match->id)->count())->toBe(0);
});

it('deletes the Event when match.status transitions to cancelled', function (): void {
    $match = GameMatch::factory()->create(['is_public' => true, 'status' => 'open']);
    expect(Event::where('eventable_type', GameMatch::class)
        ->where('eventable_id', $match->id)->count())->toBe(1);

    $match->update(['status' => 'cancelled']);

    expect(Event::where('eventable_type', GameMatch::class)
        ->where('eventable_id', $match->id)->count())->toBe(0);
});

it('updates the Event title when match.title is edited', function (): void {
    $match = GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'title' => ['en' => 'Original Title'],
    ]);

    $match->setTranslation('title', 'en', 'New Title');
    $match->save();

    $event = Event::where('eventable_type', GameMatch::class)
        ->where('eventable_id', $match->id)
        ->firstOrFail();

    expect($event->getTranslation('title', 'en'))->toBe('New Title');
});

it('updates the Event starts_at when match.scheduled_at is edited', function (): void {
    $match = GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
        'scheduled_at' => '2026-06-15 20:00:00',
    ]);

    $match->update(['scheduled_at' => '2026-07-20 18:30:00']);

    $event = Event::where('eventable_type', GameMatch::class)
        ->where('eventable_id', $match->id)
        ->firstOrFail();

    expect($event->starts_at->toIso8601String())
        ->toBe($match->fresh()->scheduled_at->toIso8601String());
});

it('deletes the Event when the match is hard-deleted', function (): void {
    $match = GameMatch::factory()->create(['is_public' => true, 'status' => 'open']);
    $matchId = $match->id;
    expect(Event::where('eventable_id', $matchId)->count())->toBe(1);

    $match->delete();

    expect(Event::where('eventable_type', GameMatch::class)
        ->where('eventable_id', $matchId)->count())->toBe(0);
});

it('re-creates the Event row when is_public is flipped back to true (updateOrCreate idempotent)', function (): void {
    $match = GameMatch::factory()->create(['is_public' => true, 'status' => 'open']);
    expect(Event::where('eventable_id', $match->id)->count())->toBe(1);

    // Flip OFF — observer deletes the Event.
    $match->update(['is_public' => false]);
    expect(Event::where('eventable_id', $match->id)->count())->toBe(0);

    // Flip ON — observer recreates exactly one Event row (UNIQUE never fires).
    $match->update(['is_public' => true]);
    expect(Event::where('eventable_type', GameMatch::class)
        ->where('eventable_id', $match->id)->count())->toBe(1);
});
