<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Source: .planning/phases/09-polish/09-07-PLAN.md task 1 +
 *         09-RESEARCH.md § Moderator Tooling — permission matrix +
 *         CLAUDE.md §6 / Pitfall 4 (Spatie permission guard MUST match
 *         Filament panel guard — `web`).
 *
 * Seeds the `moderator` role + 5 permissions for Phase 9 Wave 5:
 *
 *   moderate-users      — UserResource ban + unban BulkActions (plan 09-07 task 2)
 *   moderate-disputes   — MatchDisputeResource + MatchResource mark_cancelled (task 2)
 *   moderate-content    — ArticleResource flag/hide (plan 09-11 reserved)
 *   view-reports        — AbuseReportResource list (plan 09-11)
 *   manage-reports      — AbuseReportResource pending → actioned transitions (plan 09-11)
 *
 * Open Question 10 LOCKED (09-RESEARCH.md):
 *   The `moderator` role does NOT inherit from `super-admin`. Super-admin has
 *   its own permission grants (PermissionSeeder L49-56) and adding moderator
 *   permissions to it is intentionally left out here — super-admin already gets
 *   admin-access + every other locked permission. Adding moderator perms to
 *   super-admin would happen in plan 09-11 (or a follow-up admin seeder), NOT
 *   here, because this seeder MUST be idempotent and additive-only — if a
 *   future admin re-syncs super-admin permissions, this seeder must not
 *   accidentally narrow them.
 *
 * Idempotent: Permission::findOrCreate + Role::findOrCreate + syncPermissions
 * mean re-running the seeder is a no-op when state is already correct, and a
 * heal when permissions have been deleted.
 *
 * Pitfall 4 mitigation: every permission + role is created with
 * `guard_name='web'` so Filament's `web` panel guard resolves them.
 */
class ModeratorRoleSeeder extends Seeder
{
    /** @var list<string> */
    public const MODERATOR_PERMISSIONS = [
        'moderate-users',
        'moderate-disputes',
        'moderate-content',
        'view-reports',
        'manage-reports',
    ];

    public function run(): void
    {
        // Reset cached roles + permissions so the seeder is idempotent across re-runs.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MODERATOR_PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $moderator = Role::findOrCreate('moderator', 'web');

        // Pin the moderator role to EXACTLY the 5 permissions above — never
        // sync `Permission::all()` which would silently inherit unrelated
        // perms (admin-access, articles.*, etc.). syncPermissions also REMOVES
        // any perms not in the whitelist on re-run; that is intentional —
        // moderator scope is the 5 perms here, anything else is admin work.
        $moderator->syncPermissions(
            Permission::whereIn('name', self::MODERATOR_PERMISSIONS)->get()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
