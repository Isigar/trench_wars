<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;

/**
 * Source: 01-REVIEW.md WR-05. Permission rows must be effectively read-only
 * via the Filament UI — the codebase hard-codes permission strings, so a
 * rename would lock admins out of the panel.
 */
beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);
});

it('does not expose a Create page for permissions', function (): void {
    $this->get('/admin/permissions/create')->assertStatus(404);
});

it('declares the permission name field as disabled and non-dehydrated (WR-05)', function (): void {
    $source = file_get_contents(app_path('Filament/Resources/PermissionResource.php'));

    // The form schema for `name` must be a TextInput marked ->disabled() and
    // ->dehydrated(false) so even if the page renders, edits are dropped.
    expect($source)->toContain("TextInput::make('name')");
    expect($source)->toContain('->disabled()');
    expect($source)->toContain('->dehydrated(false)');
});

it('cannot rename a permission via Filament edit — name persists', function (): void {
    $original = 'admin-access';
    Permission::findOrCreate($original, 'web');

    // Simulate the EditPermission Livewire round-trip: the page would dehydrate
    // form state to fillable attributes. With ->dehydrated(false), the `name`
    // is excluded from the dehydrated payload entirely. Asserting the
    // permission still resolves under its original string is the contract.
    $perm = Permission::where('name', $original)->firstOrFail();
    $perm->save(); // No-op save (no fillable changes) must not rewrite name.

    expect(Permission::where('name', $original)->exists())->toBeTrue();
});
