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
 * Extended by 07-04-PLAN.md: 6 new article+category permissions + cms-editor
 * role grants. Open Question 2 LOCKED inline (artisan bootstrap pattern lives
 * in MakeCmsEditorCommand). Open Question 3 LOCKED via plan 07-03 CategorySeeder
 * (4 starter categories: News, Match Reports, Tournament Updates, Community).
 *
 * Idempotent — safe to run on a populated database (uses findOrCreate +
 * syncPermissions).
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles + permissions so the seeder is idempotent across re-runs.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'admin-access',         // Gates Filament panel access (plan 01-12).
            'audit.view',           // Read access to /admin/audit (plan 01-14).
            'articles.view',        // Read drafts in Filament (plan 07-05).
            'articles.create',      // Author new articles (plan 07-05).
            'articles.update',      // Edit own drafts (plan 07-05); editorial override via articles.publish.
            'articles.publish',     // Move status draft → scheduled/published (plan 07-06 observer).
            'articles.delete',      // Soft-delete articles (super-admin only; plan 07-12 retention concerns).
            'categories.manage',    // Full CRUD on categories (plan 07-05).
            // Phase 8 plan 08-09 — gates MatchServerResource in the Filament admin
            // panel. League IT staff manage CRCON server registrations + run
            // Test Connection actions (T-08-09-03 mitigation).
            'manage-rcon',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        // Whitelist explicitly — never sync Permission::all(). Future migrations
        // or admin-edited rows must not silently inherit super-admin privileges.
        // Keep this list in lockstep with MakeAdminCommand::handle().
        // Super-admin inherits ALL 8 permissions including articles.delete.
        $superAdmin->syncPermissions(
            Permission::whereIn('name', $permissions)->get()
        );

        // Phase 7 cms-editor role — Open Question 2 LOCKED via MakeCmsEditorCommand
        // (artisan bootstrap pattern mirrors Phase 1 trenchwars:make-admin).
        // Open Question 3 LOCKED via plan 07-03 CategorySeeder.
        //
        // EXPLICITLY OMITS 'articles.delete' — soft-delete is super-admin only
        // per T-07-04-01 (audit retention; plan 07-12 sitemap concerns).
        $cmsEditor = Role::findOrCreate('cms-editor', 'web');
        $cmsEditor->syncPermissions(
            Permission::whereIn('name', [
                'admin-access',
                'articles.view',
                'articles.create',
                'articles.update',
                'articles.publish',
                'categories.manage',
            ])->get()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
