<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionSeeder;

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
