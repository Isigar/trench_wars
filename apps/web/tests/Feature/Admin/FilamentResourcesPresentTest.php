<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionSeeder;

/**
 * Source: .planning/phases/01-foundations/01-13-PLAN.md task 2.
 *
 * Verifies the four P1 Filament resources (User, Player, Role, Permission) are
 * registered with the AdminPanelProvider and reachable for an admin user.
 *
 * Also locks in the "no Create page" contract for Users + Players (D-002 + plan 09).
 */
beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);
});

it('registers UserResource at /admin/users', function (): void {
    $this->get('/admin/users')->assertStatus(200);
});

it('registers PlayerResource at /admin/players', function (): void {
    $this->get('/admin/players')->assertStatus(200);
});

it('registers RoleResource at /admin/roles', function (): void {
    $this->get('/admin/roles')->assertStatus(200);
});

it('registers PermissionResource at /admin/permissions', function (): void {
    $this->get('/admin/permissions')->assertStatus(200);
});

it('does not register a Create route for Users or Players', function (): void {
    // Users come via OAuth (D-002); Players come via first-login (plan 09).
    $this->get('/admin/users/create')->assertStatus(404);
    $this->get('/admin/players/create')->assertStatus(404);
});
