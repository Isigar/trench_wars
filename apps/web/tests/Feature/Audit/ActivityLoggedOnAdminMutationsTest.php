<?php

declare(strict_types=1);

/*
| Source: 01-14-PLAN.md task 2 + 01-VALIDATION.md "ActivityLoggedOnAdminMutationsTest".
|
| Verifies the LogsActivity trait writes the expected activity_log rows when
| User and Player models are mutated, and that User suppresses noisy
| last_login_at-only changes (D-002 / D-012; CLAUDE.md §6 append-only audit).
*/

use App\Models\Player;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
});

it('logs an activity row when a User is updated', function (): void {
    $target = User::factory()->create(['username' => 'before']);

    $this->actingAs($this->admin);

    $target->update(['username' => 'after']);

    $log = Activity::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $target->id)
        ->where('event', 'updated')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->causer_id)->toBe($this->admin->id);
    // v5 writes attribute diffs to `attribute_changes` (was `properties.attributes` in v4).
    expect($log->attribute_changes->toArray())->toHaveKey('attributes');
    expect($log->attribute_changes->toArray()['attributes'])->toHaveKey('username');
});

it('does NOT log when only last_login_at changes', function (): void {
    $user = User::factory()->create();

    Activity::query()->delete();
    $user->update(['last_login_at' => now()]);

    expect(Activity::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->count())->toBe(0);
});

it('logs Player updates', function (): void {
    $player = Player::factory()->create();

    $this->actingAs($this->admin);
    $player->update(['display_name' => 'Updated Name']);

    expect(Activity::query()
        ->where('subject_type', Player::class)
        ->where('subject_id', $player->id)
        ->where('event', 'updated')
        ->count())->toBe(1);
});
