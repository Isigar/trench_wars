<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Source: 01-RESEARCH.md § Code Examples "Spatie permission seeding"
 * + 01-CONTEXT.md § Filament panel & gating (admin-access permission).
 *
 * Idempotent — safe to run on a populated database (uses findOrCreate).
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles + permissions so the seeder is idempotent across re-runs.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'admin-access',  // Gates Filament panel access (plan 12).
            'audit.view',    // Read access to /admin/audit (plan 14).
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $superAdmin->syncPermissions(Permission::all());

        // Phase 7+ role placeholder — no permissions yet.
        Role::findOrCreate('cms-editor', 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
