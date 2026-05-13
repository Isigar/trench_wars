<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\Event;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameRole;
use App\Models\MatchAccessRule;
use App\Models\MatchResult;
use App\Models\MatchSlot;
use App\Models\User;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/04-matches-manual/04-03-PLAN.md task 3.
| Analog: apps/web/tests/Feature/Models/GameMatchTypeModelTest.php
|         + apps/web/tests/Feature/Models/ClanMembershipModelTest.php
| Replaces the Wave 0 RED stub from plan 04-01 (Wave 0 marker removed).
|
| Model class is `App\Models\GameMatch` (NOT `Match` — PHP 8.4 reserves `match` as a
| keyword; verified by `php -r "class Match {}"` parse error). DB table is `matches`.
*/

it('creates a valid match via factory', function (): void {
    $match = GameMatch::factory()->create();

    expect($match->exists)->toBeTrue();
    expect($match->status)->toBe('open');
    expect($match->is_public)->toBeTrue();
    expect($match->scheduled_at)->not->toBeNull();
    expect($match->getTranslation('title', 'en'))->not->toBe('');
});

it('round-trips title through HasTranslations', function (): void {
    $match = GameMatch::factory()->create(['title' => ['en' => 'Friday scrim']]);

    $match->setTranslation('title', 'en', 'Friday night scrim');
    $match->save();

    expect($match->fresh()->getTranslation('title', 'en'))->toBe('Friday night scrim');
});

it('round-trips description through HasTranslations independently of title', function (): void {
    $match = GameMatch::factory()->create([
        'title' => ['en' => 'Tournament Final'],
        'description' => ['en' => 'Original desc'],
    ]);

    $match->setTranslation('description', 'en', 'Best of three, no subs.');
    $match->save();

    $reloaded = $match->fresh();
    expect($reloaded->getTranslation('description', 'en'))->toBe('Best of three, no subs.');
    expect($reloaded->getTranslation('title', 'en'))->toBe('Tournament Final');
});

it('enforces matches_status_check CHECK constraint at the DB layer', function (): void {
    expect(fn () => GameMatch::factory()->create(['status' => 'banana']))
        ->toThrow(QueryException::class);
});

it('accepts each valid status enum value', function (): void {
    foreach (['draft', 'open', 'locked', 'played', 'cancelled'] as $status) {
        $match = GameMatch::factory()->create(['status' => $status]);
        expect($match->status)->toBe($status);
    }
});

it('exposes gameMatchType, organiser, hostClan BelongsTo relations', function (): void {
    $matchType = GameMatchType::factory()->create();
    $organiser = User::factory()->create();
    $hostClan = Clan::factory()->create();

    $match = GameMatch::factory()->create([
        'game_match_type_id' => $matchType->id,
        'organiser_user_id' => $organiser->id,
        'host_clan_id' => $hostClan->id,
    ]);

    expect($match->gameMatchType?->id)->toBe($matchType->id);
    expect($match->organiser?->id)->toBe($organiser->id);
    expect($match->hostClan?->id)->toBe($hostClan->id);
});

it('exposes slots HasMany ordered by sort_order then slot_index', function (): void {
    $match = GameMatch::factory()->create();
    $role = GameRole::factory()->create();

    $slotB = MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 5,
        'sort_order' => 0,
    ]);
    $slotA = MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 1,
        'sort_order' => 0,
    ]);

    $slots = $match->fresh()->slots;
    expect($slots->pluck('id')->all())->toBe([$slotA->id, $slotB->id]);
});

it('exposes accessRules HasMany, result HasOne, and event MorphOne relations', function (): void {
    $match = GameMatch::factory()->create();
    $rule = MatchAccessRule::factory()->create(['match_id' => $match->id]);
    $result = MatchResult::factory()->create(['match_id' => $match->id]);
    $event = Event::factory()->create([
        'eventable_type' => GameMatch::class,
        'eventable_id' => $match->id,
    ]);

    $reloaded = $match->fresh();
    expect($reloaded->accessRules->pluck('id')->all())->toContain($rule->id);
    expect($reloaded->result?->id)->toBe($result->id);
    expect($reloaded->event?->id)->toBe($event->id);
});

it('cascades delete to match_slots when the match is deleted', function (): void {
    $match = GameMatch::factory()->create();
    $role = GameRole::factory()->create();
    $slot = MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 0,
    ]);
    $slotId = $slot->id;

    $match->delete();

    expect(MatchSlot::where('id', $slotId)->exists())->toBeFalse();
});

it('logs activity on create (D-012)', function (): void {
    $match = GameMatch::factory()->create();

    $activity = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});
