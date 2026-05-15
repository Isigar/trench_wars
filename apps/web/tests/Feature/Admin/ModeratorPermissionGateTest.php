<?php

declare(strict_types=1);

use App\Filament\Resources\MatchResource;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Database\Seeders\ModeratorRoleSeeder;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
| Source: .planning/phases/09-polish/09-07-PLAN.md task 2 (Wave 5).
|
| Replaces the Wave 0 RED stub. Locks SC-3 permission-gate invariants for the
| moderator surface:
|
|   1. Non-moderator users (admin-access only) do NOT see the ban / unban
|      BulkActions on UserResource (T-09-07-01 — elevation gate).
|   2. Moderator users (assigned to the `moderator` role) DO see them.
|   3. Non-moderators cannot access /admin/match-disputes — 403 / redirect.
|   4. Moderators can access /admin/match-disputes.
|   5. Super-admin (PermissionSeeder) retains admin-access — no regression of
|      the Phase 1 admin gate when ModeratorRoleSeeder runs.
|   6. Spatie permission default_guard=web matches Filament panel guard
|      (CLAUDE.md s6 / Pitfall 4 regression guard).
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(ModeratorRoleSeeder::class);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ---------------------------------------------------------------------------
// UserResource ban / unban BulkAction visibility gate
// ---------------------------------------------------------------------------

it('non-moderator user cannot see UserResource ban BulkAction', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo('admin-access');
    $this->actingAs($admin);

    Livewire::test(UserResource\Pages\ListUsers::class)
        ->assertTableBulkActionHidden('ban')
        ->assertTableBulkActionHidden('unban');
});

it('moderator user can see ban + unban BulkActions', function (): void {
    $moderator = User::factory()->create();
    $moderator->givePermissionTo('admin-access');
    $moderator->assignRole('moderator');
    $this->actingAs($moderator);

    Livewire::test(UserResource\Pages\ListUsers::class)
        ->assertTableBulkActionVisible('ban')
        ->assertTableBulkActionVisible('unban');
});

it('non-moderator user cannot see MatchResource mark_cancelled BulkAction', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo('admin-access');
    $this->actingAs($admin);

    Livewire::test(MatchResource\Pages\ListMatches::class)
        ->assertTableBulkActionHidden('mark_cancelled');
});

it('moderator user can see MatchResource mark_cancelled BulkAction', function (): void {
    $moderator = User::factory()->create();
    $moderator->givePermissionTo('admin-access');
    $moderator->assignRole('moderator');
    $this->actingAs($moderator);

    Livewire::test(MatchResource\Pages\ListMatches::class)
        ->assertTableBulkActionVisible('mark_cancelled');
});

// ---------------------------------------------------------------------------
// /admin/match-disputes route gate
// ---------------------------------------------------------------------------

it('non-moderator user cannot access /admin/match-disputes', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo('admin-access');
    $this->actingAs($admin);

    // Filament resources gate via canViewAny(); when false, the route
    // returns 403 (Filament aborts with Forbidden when canViewAny is false).
    $this->get('/admin/match-disputes')->assertForbidden();
});

it('moderator user can access /admin/match-disputes', function (): void {
    $moderator = User::factory()->create();
    $moderator->givePermissionTo('admin-access');
    $moderator->assignRole('moderator');
    $this->actingAs($moderator);

    $this->get('/admin/match-disputes')->assertOk();
});

// ---------------------------------------------------------------------------
// Pitfall 4 regression — Spatie default_guard MUST match Filament panel guard
// ---------------------------------------------------------------------------

it('Spatie permission default_guard=web matches Filament panel guard (Pitfall 4)', function (): void {
    // The actual config key in spatie/laravel-permission is `default_guard_name`
    // (not `default_guard`). CLAUDE.md s6 requires it to be `'web'` so the
    // Filament panel guard (also `web`) resolves permissions consistently.
    expect(config('permission.default_guard_name'))->toBe('web');

    // Permissions seeded via ModeratorRoleSeeder MUST have guard_name='web' so
    // Filament's `web` panel guard resolves them. A guard mismatch would render
    // hasPermissionTo('moderate-users') silently false even for assigned
    // moderators.
    $rows = Permission::query()
        ->whereIn('name', ModeratorRoleSeeder::MODERATOR_PERMISSIONS)
        ->get();
    foreach ($rows as $perm) {
        expect($perm->guard_name)->toBe('web');
    }

    $role = Role::query()->where('name', 'moderator')->first();
    expect($role)->not->toBeNull()
        ->and($role->guard_name)->toBe('web');
});

// ---------------------------------------------------------------------------
// Super-admin regression — admin-access still functional
// ---------------------------------------------------------------------------

it('super-admin retains admin-access permission after ModeratorRoleSeeder runs', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    expect($superAdmin->hasPermissionTo('admin-access'))->toBeTrue()
        // Super-admin does NOT inherit moderator perms (Open Question 10 LOCKED —
        // separate roles, separate gates). admin-access alone is sufficient
        // for the panel, but moderator-specific resources still require
        // moderator perms.
        ->and($superAdmin->hasPermissionTo('moderate-users'))->toBeFalse();
});
