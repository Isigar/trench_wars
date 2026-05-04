<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionSeeder;

it('redirects unauthenticated /admin requests to Discord OAuth', function (): void {
    $response = $this->get('/admin');

    $response->assertRedirect(route('auth.discord.redirect'));
});

it('boots the admin panel without errors when accessed by an admin', function (): void {
    $this->seed(PermissionSeeder::class);

    $admin = User::factory()->create();
    $admin->givePermissionTo('admin-access');

    $this->actingAs($admin)
        ->get('/admin')
        ->assertStatus(200);
});
