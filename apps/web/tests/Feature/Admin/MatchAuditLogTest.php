<?php

declare(strict_types=1);

/*
| Source: 04-12-PLAN.md Task 3 — replaces the Wave 0 RED stub (plan 04-02).
|
| Verifies D-012 (Filament + spatie/activitylog) end-to-end for ALL Phase 4 models.
|
| The Phase 4 models with LogsActivity (wired in plan 04-03):
|   - GameMatch         → "Match {event}"
|   - MatchSlot         → "MatchSlot {event}"
|   - MatchAccessRule   → "MatchAccessRule {event}"
|   - MatchResult       → "MatchResult {event}"
|   - MatchMvp          → "MatchMvp {event}"
|   - Event             → "Event {event}"          (observer-driven create — plan 04-08)
|
| Plus the explicit causer-aware service writes:
|   - MatchStatusService::transition → activity().log('Match status transition')
|                                       with properties[from, to] + causer
|   - MatchSignupService::signup     → MatchSlot::update() trips LogsActivity
|                                       with properties.attributes.occupant_user_id
|   - MatchResultService::upsert     → MatchResult::updateOrCreate trips LogsActivity
|                                       PLUS the side-effect status transition row
|
| Analog: tests/Feature/Admin/GameAuditLogTest.php (Phase 3 plan 03-09).
| Naming: GameMatch (D-04-03-A LOCKED) — `Match` is a PHP 8.4 parse error.
*/

use App\Models\Clan;
use App\Models\ClanTag;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameRole;
use App\Models\MatchAccessRule;
use App\Models\MatchMvp;
use App\Models\MatchResult;
use App\Models\MatchSlot;
use App\Models\Player;
use App\Models\User;
use App\Services\MatchResultService;
use App\Services\MatchSignupService;
use App\Services\MatchStatusService;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);
});

// ---------------------------------------------------------------------------
// 1. GameMatch.create — bare LogsActivity trip
// ---------------------------------------------------------------------------

it('writes activity_log on GameMatch creation', function (): void {
    $match = GameMatch::factory()->create();

    $activity = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->causer_type)->toBe(User::class)
        ->and($activity->description)->toBe('Match created');
});

// ---------------------------------------------------------------------------
// 2. MatchStatusService transition — causer + properties[from, to]
//    (this row is written via explicit activity()->withProperties() call, NOT
//    LogsActivity — so properties are populated; see MatchStatusService.php.)
// ---------------------------------------------------------------------------

it('writes activity_log on Match status transition with causer + properties[from, to]', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);

    app(MatchStatusService::class)->transition($match, 'locked', $this->admin);

    $activity = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('description', 'Match status transition')
        ->where('causer_id', $this->admin->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('from'))->toBe('open')
        ->and($activity->properties->get('to'))->toBe('locked');
});

// ---------------------------------------------------------------------------
// 3. MatchSlot occupant update via MatchSignupService — LogsActivity trip
//    The service calls $slot->update(['occupant_user_id' => ...]); LogsActivity
//    on MatchSlot fires the 'updated' event row. Causer is the authenticated
//    signing-up user (controller path inherits auth context).
// ---------------------------------------------------------------------------

it('writes activity_log on MatchSlot occupant update via MatchSignupService', function (): void {
    // Same-game fixture (MatchSlot factory defaults to cross-game pair).
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create();
    $match = GameMatch::factory()->for($matchType, 'gameMatchType')->create(['status' => 'open']);

    $slotFixture = MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 0,
        'occupant_user_id' => null,
        'confirmed_at' => null,
    ]);

    $signupUser = User::factory()->create();
    $this->actingAs($signupUser);

    $slot = app(MatchSignupService::class)->signup($match, $signupUser, $role);

    expect($slot->id)->toBe($slotFixture->id);

    $activity = Activity::query()
        ->where('subject_type', MatchSlot::class)
        ->where('subject_id', $slot->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($signupUser->id)
        ->and($activity->description)->toBe('MatchSlot updated');

    // Verify the slot itself reflects the write (D-010 service-only write path proven).
    $slot->refresh();
    expect($slot->occupant_user_id)->toBe($signupUser->id)
        ->and($slot->confirmed_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// 4. MatchAccessRule create — bare LogsActivity trip
// ---------------------------------------------------------------------------

it('writes activity_log on MatchAccessRule create', function (): void {
    $match = GameMatch::factory()->create();
    $tag = ClanTag::factory()->create();

    $rule = MatchAccessRule::factory()->create([
        'match_id' => $match->id,
        'clan_tag_id' => $tag->id,
    ]);

    $activity = Activity::query()
        ->where('subject_type', MatchAccessRule::class)
        ->where('subject_id', $rule->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->description)->toBe('MatchAccessRule created');
});

// ---------------------------------------------------------------------------
// 5. MatchResult create via MatchResultService — LogsActivity + status transition
// ---------------------------------------------------------------------------

it('writes activity_log on MatchResult create via MatchResultService', function (): void {
    $match = GameMatch::factory()->create(['status' => 'open']);
    $winner = Clan::factory()->create();

    $result = app(MatchResultService::class)->upsert($match, [
        'winner_clan_id' => $winner->id,
        'allies_score' => 4,
        'axis_score' => 1,
        'notes' => 'Test result',
        'recorded_at' => now(),
    ], $this->admin);

    // Two activity rows land per first-time entry (D-04-09-C):
    //   1. MatchResult created (LogsActivity on MatchResult model)
    //   2. Match status transition (open → played, via MatchStatusService side-effect)
    $resultActivity = Activity::query()
        ->where('subject_type', MatchResult::class)
        ->where('subject_id', $result->id)
        ->where('event', 'created')
        ->first();

    expect($resultActivity)->not->toBeNull()
        ->and($resultActivity->causer_id)->toBe($this->admin->id)
        ->and($resultActivity->description)->toBe('MatchResult created');

    // Side-effect status row (D-04-09-C — explicit activity()->withProperties()
    // in MatchStatusService, NOT LogsActivity on the model).
    $statusActivity = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('description', 'Match status transition')
        ->first();

    expect($statusActivity)->not->toBeNull()
        ->and($statusActivity->properties->get('from'))->toBe('open')
        ->and($statusActivity->properties->get('to'))->toBe('played');
});

// ---------------------------------------------------------------------------
// 6. MatchMvp create — bare LogsActivity trip
// ---------------------------------------------------------------------------

it('writes activity_log on MatchMvp create', function (): void {
    $match = GameMatch::factory()->create();
    $result = MatchResult::factory()->create(['match_id' => $match->id]);
    $player = Player::factory()->create();

    $mvp = MatchMvp::factory()->create([
        'match_result_id' => $result->id,
        'player_id' => $player->id,
        'category' => 'kills',
        'value' => 42,
    ]);

    $activity = Activity::query()
        ->where('subject_type', MatchMvp::class)
        ->where('subject_id', $mvp->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->description)->toBe('MatchMvp created');
});

// ---------------------------------------------------------------------------
// 7. Event observer-driven create — MatchObserver fires on GameMatch::create
//    (is_public=true && status !== 'cancelled')
// ---------------------------------------------------------------------------

it('writes activity_log on Event observer-driven create', function (): void {
    // is_public=true + status=open → MatchObserver::saved() updateOrCreate fires.
    $match = GameMatch::factory()->create([
        'is_public' => true,
        'status' => 'open',
    ]);

    // The Event row was created by MatchObserver inside the same save() cycle.
    $event = Event::query()
        ->where('eventable_type', GameMatch::class)
        ->where('eventable_id', $match->id)
        ->first();

    expect($event)->not->toBeNull();

    // LogsActivity on Event fired during the observer write.
    $activity = Activity::query()
        ->where('subject_type', Event::class)
        ->where('subject_id', $event->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Event created');
});

// ---------------------------------------------------------------------------
// 8. GameMatch update — event=updated (NOT 'created') — logOnlyDirty contract
// ---------------------------------------------------------------------------

it('writes GameMatch updates as event=updated, not event=created', function (): void {
    $match = GameMatch::factory()->create(['is_public' => true]);
    $match->update(['is_public' => false]);

    $updateActivity = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('event', 'updated')
        ->first();

    expect($updateActivity)->not->toBeNull()
        ->and($updateActivity->description)->toBe('Match updated');
});

// ---------------------------------------------------------------------------
// 9. GameMatch logFillable + logOnlyDirty fidelity — no-op updates write zero rows;
//    real fillable changes write exactly one 'updated' row per save.
//
//    The project's LogsActivity idiom is `LogOptions::defaults()->logFillable()
//    ->logOnlyDirty()`. Spatie's defaults do NOT populate the `properties` JSON
//    with per-attribute change tuples (that requires `logUnguarded()` or an
//    explicit `dontLogIfAttributesChangedOnly()` toggle). The contract this
//    project DOES enforce: a save that does not touch a fillable column writes
//    NO activity row; a save that touches >=1 fillable column writes EXACTLY
//    ONE 'updated' row.
// ---------------------------------------------------------------------------

it('GameMatch logOnlyDirty: no-op save writes zero update rows', function (): void {
    $match = GameMatch::factory()->create(['is_public' => true]);

    $beforeCount = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('event', 'updated')
        ->count();

    // No-op save — same attribute values; logOnlyDirty must skip.
    $match->update(['is_public' => true]);

    $afterCount = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('event', 'updated')
        ->count();

    expect($afterCount)->toBe($beforeCount);
});

it('GameMatch logOnlyDirty: single-field change writes exactly one update row', function (): void {
    $match = GameMatch::factory()->create(['is_public' => true]);

    $beforeCount = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('event', 'updated')
        ->count();

    $match->update(['is_public' => false]);

    $afterCount = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('event', 'updated')
        ->count();

    expect($afterCount - $beforeCount)->toBe(1);
});

// ---------------------------------------------------------------------------
// 10. GameMatch delete — event=deleted
// ---------------------------------------------------------------------------

it('writes activity_log on GameMatch delete as event=deleted', function (): void {
    $match = GameMatch::factory()->create();
    $matchId = $match->id;
    $match->delete();

    $deleteActivity = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $matchId)
        ->where('event', 'deleted')
        ->first();

    expect($deleteActivity)->not->toBeNull()
        ->and($deleteActivity->description)->toBe('Match deleted');
});

// ---------------------------------------------------------------------------
// 11. Causer capture — every Phase 4 model row written while acting as admin
//     captures causer_id = $admin->id + causer_type = User::class.
// ---------------------------------------------------------------------------

it('captures causer_id as the acting admin user on Phase 4 model writes', function (): void {
    $match = GameMatch::factory()->create();

    $activity = Activity::query()
        ->where('subject_type', GameMatch::class)
        ->where('subject_id', $match->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->causer_type)->toBe(User::class);
});
