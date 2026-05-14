<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

it('assigns cms-editor role to user by discord_id', function (): void {
    $user = User::factory()->create(['discord_id' => '101102103']);

    $this->artisan('trenchwars:make-cms-editor', ['discord_id' => '101102103'])
        ->expectsOutputToContain('cms-editor granted to')
        ->assertExitCode(0);

    $user = $user->fresh();
    expect($user->hasRole('cms-editor'))->toBeTrue();
    expect($user->can('admin-access'))->toBeTrue();
    expect($user->can('articles.publish'))->toBeTrue();
    expect($user->can('articles.delete'))->toBeFalse();
});

it('errors when discord_id is not found', function (): void {
    $this->artisan('trenchwars:make-cms-editor', ['discord_id' => '999999999'])
        ->expectsOutputToContain('not found')
        ->assertExitCode(1);
});

it('is idempotent — second call does not duplicate role assignment', function (): void {
    $user = User::factory()->create(['discord_id' => '202203204']);

    $this->artisan('trenchwars:make-cms-editor', ['discord_id' => '202203204'])->assertExitCode(0);
    $this->artisan('trenchwars:make-cms-editor', ['discord_id' => '202203204'])->assertExitCode(0);

    // Exactly one model_has_roles row for this user.
    expect($user->fresh()->roles()->count())->toBe(1);
    expect($user->fresh()->hasRole('cms-editor'))->toBeTrue();
});

it('writes an activity_log row on first grant only (D-012 audit)', function (): void {
    $user = User::factory()->create(['discord_id' => '303304305']);

    $this->artisan('trenchwars:make-cms-editor', ['discord_id' => '303304305'])->assertExitCode(0);
    $this->artisan('trenchwars:make-cms-editor', ['discord_id' => '303304305'])->assertExitCode(0);

    $activities = Activity::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('description', 'cms-editor role granted via CLI')
        ->get();

    // Only the FIRST call writes the activity row; the second call short-circuits
    // because hasRole('cms-editor') is already true.
    expect($activities)->toHaveCount(1);
    expect($activities->first()->properties->get('command'))->toBe('trenchwars:make-cms-editor');
    expect($activities->first()->properties->get('discord_id'))->toBe('303304305');
});
