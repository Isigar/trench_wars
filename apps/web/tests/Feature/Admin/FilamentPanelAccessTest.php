<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

it('returns 403 for users without admin-access permission', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('returns 200 for users with admin-access permission', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('admin-access');

    $this->actingAs($user)
        ->get('/admin')
        ->assertStatus(200);
});

it('does NOT include the Filament built-in login route', function (): void {
    // Open Question #4: ->login() is dropped, so /admin/login should not exist (200).
    // Unauthenticated visit redirects through Filament's auth middleware to the
    // app-level Authenticate redirect target (Discord OAuth) — i.e. NOT a 200 form.
    $response = $this->get('/admin/login');

    expect($response->status())->not->toBe(200);
});
