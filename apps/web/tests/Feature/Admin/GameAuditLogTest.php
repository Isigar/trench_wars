<?php

declare(strict_types=1);

/*
 * Verifies D-012 (Filament + spatie/activitylog) end-to-end for Phase 3 models per plan 03-09.
 *
 * The four Phase 3 models (Game, GameRole, GameMatchType, GameMatchTypeRoleLimit) all use the
 * Spatie\Activitylog\Models\Concerns\LogsActivity trait (wired in plan 03-03). This test exercises
 * the trait → activity_log DB pipe directly via Eloquent — it does NOT exercise the Filament UI
 * (that is plan 03-08's scope). The integration contract proven here:
 *
 *   1. Create writes an activity_log row with subject_type = FQN + event = 'created'.
 *   2. Update writes an activity_log row with event = 'updated' (logOnlyDirty behaviour).
 *   3. The acting authenticated user (set via actingAs()) is captured as causer_id.
 *   4. The cross-game saving() guard on GameMatchTypeRoleLimit is bypassed via same-game fixtures
 *      so the success-path activity_log row can be asserted (plan 03-03 task 3 covers the failure path).
 */

use App\Models\Game;
use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use App\Models\GameRole;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);
});

it('writes activity_log row on Game::create with subject_type=App\\Models\\Game and event=created', function (): void {
    $game = Game::factory()->create();

    $activity = Activity::query()
        ->where('subject_type', Game::class)
        ->where('subject_id', $game->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});

it('writes activity_log row on GameRole::create', function (): void {
    $role = GameRole::factory()->create();

    $activity = Activity::query()
        ->where('subject_type', GameRole::class)
        ->where('subject_id', $role->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});

it('writes activity_log row on GameMatchType::create', function (): void {
    $matchType = GameMatchType::factory()->create();

    $activity = Activity::query()
        ->where('subject_type', GameMatchType::class)
        ->where('subject_id', $matchType->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});

it('writes activity_log row on GameMatchTypeRoleLimit::create (same-game scenario)', function (): void {
    // Same-game fixtures — required to bypass the saving() cross-game guard on GameMatchTypeRoleLimit
    // (see apps/web/app/Models/GameMatchTypeRoleLimit.php booted() listener; plan 03-03 task 3 covers
    // the cross-game failure path). The default factory generates two distinct Games which throws.
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create();

    $limit = GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
    ]);

    $activity = Activity::query()
        ->where('subject_type', GameMatchTypeRoleLimit::class)
        ->where('subject_id', $limit->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
});

it('logs Game updates as event=updated, not event=created', function (): void {
    $game = Game::factory()->create(['is_active' => true]);
    $game->update(['is_active' => false]);

    $updateActivity = Activity::query()
        ->where('subject_type', Game::class)
        ->where('subject_id', $game->id)
        ->where('event', 'updated')
        ->first();

    expect($updateActivity)->not->toBeNull();
});

it('logs deletes on Game::delete as event=deleted', function (): void {
    $game = Game::factory()->create();
    $gameId = $game->id;
    $game->delete();

    $deleteActivity = Activity::query()
        ->where('subject_type', Game::class)
        ->where('subject_id', $gameId)
        ->where('event', 'deleted')
        ->first();

    expect($deleteActivity)->not->toBeNull();
});

it('captures causer_id as the acting admin user on Game create', function (): void {
    $game = Game::factory()->create();

    $activity = Activity::query()
        ->where('subject_type', Game::class)
        ->where('subject_id', $game->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($this->admin->id);
    expect($activity->causer_type)->toBe(User::class);
});
