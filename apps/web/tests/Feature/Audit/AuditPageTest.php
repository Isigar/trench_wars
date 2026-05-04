<?php

declare(strict_types=1);

/*
| Source: 01-14-PLAN.md task 2 + 01-VALIDATION.md "AuditPageTest".
|
| Verifies the global /admin/audit Filament Page is reachable for admins, gated
| from non-admins, and renders empty-state vs populated content correctly
| (CLAUDE.md \xc2\xa76 — audit page is read-only by design; no edit/delete actions).
*/

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);
});

it('renders /admin/audit for an admin', function (): void {
    $this->get('/admin/audit')->assertStatus(200);
});

it('shows the empty state when no activity is logged', function (): void {
    Activity::query()->delete();

    $this->get('/admin/audit')
        ->assertStatus(200)
        ->assertSeeText('No activity yet');
});

it('lists activity rows', function (): void {
    $target = User::factory()->create();
    $target->update(['username' => 'logged_change']);

    $this->get('/admin/audit')
        ->assertStatus(200)
        ->assertSeeText('updated');
});

it('returns 403 for non-admin', function (): void {
    auth()->logout();
    $non = User::factory()->create();
    $this->actingAs($non);
    $this->get('/admin/audit')->assertForbidden();
});
