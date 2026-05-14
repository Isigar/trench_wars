<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Source: 07-04-PLAN.md task 1 (Open Question 2 LOCKED inline) —
 * mirrors Phase 1 plan 01-11 trenchwars:make-admin idiom verbatim.
 *
 * Bootstraps the editorial team by granting the cms-editor role to a Discord
 * user identified by their snowflake (D-002: Discord ID is canonical identity).
 *
 * Idempotent — re-running grants no additional rows because Spatie's assignRole
 * skips duplicates. Defence-in-depth: the command findOrCreate's the role +
 * permission grants even though PermissionSeeder normally creates them, so it
 * works on a freshly migrated DB without seeding.
 *
 * cms-editor permission set (per PermissionSeeder, EXPLICITLY OMITS articles.delete):
 *   admin-access | articles.view | articles.create | articles.update
 *   articles.publish | categories.manage
 */
class MakeCmsEditorCommand extends Command
{
    protected $signature = 'trenchwars:make-cms-editor {discord_id : Discord snowflake (text) of the user to elevate}';

    protected $description = 'Grant the cms-editor role to a user by discord_id (idempotent).';

    public function handle(PermissionRegistrar $registrar): int
    {
        $registrar->forgetCachedPermissions();

        $discordId = (string) $this->argument('discord_id');

        $user = User::where('discord_id', $discordId)->first();
        if ($user === null) {
            $this->error("User with discord_id={$discordId} not found. Have they logged in via Discord at least once?");

            return self::FAILURE;
        }

        // Ensure the role + its permission set exist (defence-in-depth — PermissionSeeder
        // normally creates them, but the command must work on a freshly migrated DB).
        $cmsEditorPermissions = [
            'admin-access',
            'articles.view',
            'articles.create',
            'articles.update',
            'articles.publish',
            'categories.manage',
        ];
        foreach ($cmsEditorPermissions as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $role = Role::findOrCreate('cms-editor', 'web');
        $role->syncPermissions(
            Permission::whereIn('name', $cmsEditorPermissions)->get()
        );

        // Idempotent: spatie's assignRole is naturally a no-op on duplicate, but we
        // gate explicitly so the success message + activity_log row are correct
        // on the first call only.
        $alreadyHadRole = $user->hasRole('cms-editor');
        if (! $alreadyHadRole) {
            $user->assignRole('cms-editor');

            // D-012 audit-trail: every role grant must leave an activity_log row.
            // spatie/laravel-permission's grant methods are silent by default
            // (config/permission.php events_enabled=false), so we log explicitly.
            activity()
                ->performedOn($user)
                ->withProperties([
                    'command' => 'trenchwars:make-cms-editor',
                    'discord_id' => $discordId,
                ])
                ->log('cms-editor role granted via CLI');
        }

        $registrar->forgetCachedPermissions();

        $this->info("cms-editor granted to {$user->username} (discord_id={$discordId}).");

        return self::SUCCESS;
    }
}
