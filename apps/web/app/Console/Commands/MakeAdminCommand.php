<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Source: 01-RESEARCH.md § Code Examples "Custom artisan command to create first admin"
 * + 01-CONTEXT.md "First admin seeded via php artisan trenchwars:make-admin <discord_id> (idempotent)".
 *
 * Idempotent — re-running grants no additional rows because Spatie's
 * givePermissionTo / assignRole skip duplicates. Defence-in-depth: the command
 * findOrCreate's the permission + role even though PermissionSeeder normally
 * creates them, so it works on a freshly migrated DB without seeding.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'trenchwars:make-admin {discord_id : Discord snowflake (text) of the user to elevate}';

    protected $description = 'Grant admin-access permission and super-admin role to a user (idempotent).';

    public function handle(PermissionRegistrar $registrar): int
    {
        $registrar->forgetCachedPermissions();

        $discordId = (string) $this->argument('discord_id');

        $user = User::where('discord_id', $discordId)->first();
        if ($user === null) {
            $this->error("User with discord_id={$discordId} not found. Have they logged in via Discord at least once?");

            return self::FAILURE;
        }

        // Ensure permission and role exist (defence-in-depth — PermissionSeeder normally creates them).
        Permission::findOrCreate('admin-access', 'web');
        $role = Role::findOrCreate('super-admin', 'web');

        // Whitelist the permissions super-admin holds. NEVER use Permission::all() —
        // future migrations or admin-edited rows would silently inherit privileges.
        $superAdminPermissions = ['admin-access', 'audit.view'];
        $role->syncPermissions(
            Permission::whereIn('name', $superAdminPermissions)->get()
        );

        // givePermissionTo / assignRole are idempotent in spatie/laravel-permission.
        $user->givePermissionTo('admin-access');
        $user->assignRole('super-admin');

        // D-012 audit-trail: every super-admin grant must leave an activity_log row.
        // spatie/laravel-permission's grant methods are silent by default
        // (config/permission.php events_enabled=false), so we log explicitly.
        activity()
            ->performedOn($user)
            ->withProperties([
                'command' => 'trenchwars:make-admin',
                'discord_id' => $discordId,
            ])
            ->log('Super-admin granted via CLI');

        $registrar->forgetCachedPermissions();

        $this->info("Admin granted to {$user->username} (discord_id={$discordId}).");

        return self::SUCCESS;
    }
}
