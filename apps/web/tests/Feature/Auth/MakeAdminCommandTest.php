<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

it('fails clearly when the user does not exist', function (): void {
    $this->artisan('trenchwars:make-admin', ['discord_id' => '999999999'])
        ->expectsOutputToContain('not found')
        ->assertExitCode(1);
});

it('grants admin-access + super-admin role on happy path', function (): void {
    $user = User::factory()->create(['discord_id' => '111222333']);

    $this->artisan('trenchwars:make-admin', ['discord_id' => '111222333'])
        ->expectsOutputToContain('Admin granted to')
        ->assertExitCode(0);

    $user->refresh();
    expect($user->hasPermissionTo('admin-access'))->toBeTrue();
    expect($user->hasRole('super-admin'))->toBeTrue();
});

it('is idempotent on re-run', function (): void {
    $user = User::factory()->create(['discord_id' => '444555666']);

    $this->artisan('trenchwars:make-admin', ['discord_id' => '444555666'])->assertExitCode(0);
    $this->artisan('trenchwars:make-admin', ['discord_id' => '444555666'])->assertExitCode(0);

    expect($user->fresh()->permissions()->count())->toBe(1);
    expect($user->fresh()->roles()->count())->toBe(1);
});

it('writes an activity_log row when granting super-admin (D-012)', function (): void {
    $user = User::factory()->create(['discord_id' => '777888999']);

    $this->artisan('trenchwars:make-admin', ['discord_id' => '777888999'])->assertExitCode(0);

    $activity = Activity::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('description', 'Super-admin granted via CLI')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->get('command'))->toBe('trenchwars:make-admin');
    expect($activity->properties->get('discord_id'))->toBe('777888999');
});

it('does not grant unrelated permissions to super-admin (whitelist enforced)', function (): void {
    // Simulate a permission that should NOT auto-attach to super-admin.
    Permission::findOrCreate('rogue.permission', 'web');

    $user = User::factory()->create(['discord_id' => '123987654']);
    $this->artisan('trenchwars:make-admin', ['discord_id' => '123987654'])->assertExitCode(0);

    $role = Role::findByName('super-admin', 'web');
    $names = $role->permissions()->pluck('name')->all();

    expect($names)->toContain('admin-access');
    expect($names)->toContain('audit.view');
    expect($names)->not->toContain('rogue.permission');
});
