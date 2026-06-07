<?php

declare(strict_types=1);

/*
| Ban enforcement at the auth layer (REACH-04).
|
| Closes the gap where bans were an audit record only — BanService::isCurrentlyBanned
| and User::activeBan existed but nothing in the request lifecycle invoked them, so a
| banned user kept full authenticated access. These tests assert the EnsureUserNotBanned
| middleware denies authenticated access, an active ban also blocks Filament panel access,
| and lifted/expired bans do NOT block.
*/

use App\Models\Ban;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;

it('denies a currently-banned user authenticated access and tears down the session', function (): void {
    $user = User::factory()->create();
    Ban::factory()->for($user)->create(); // default: active temporary ban

    $this->actingAs($user)
        ->get('/notifications')
        ->assertRedirect(route('home'));

    // The banned user is logged out — the session was invalidated.
    $this->assertGuest();
});

it('allows a non-banned user authenticated access', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/notifications')
        ->assertOk();
});

it('does NOT block a user whose ban has been lifted', function (): void {
    $user = User::factory()->create();
    Ban::factory()->for($user)->lifted()->create();

    $this->actingAs($user)
        ->get('/notifications')
        ->assertOk();
});

it('blocks Filament panel access for a currently-banned admin', function (): void {
    $this->seed(PermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->givePermissionTo('admin-access');

    $panel = Filament::getPanel('admin');
    expect($admin->fresh()->canAccessPanel($panel))->toBeTrue();

    Ban::factory()->for($admin)->create();
    expect($admin->fresh()->canAccessPanel($panel))->toBeFalse();
});
