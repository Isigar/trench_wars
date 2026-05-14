<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Services\MatchResultService;
use Illuminate\Database\Seeder;

/**
 * Source: .planning/phases/08-rcon-automation/08-08-PLAN.md task 1 +
 *         <interfaces> RconWorkerSystemUserSeeder block +
 *         must_haves.truths #5 (rcon-worker@system.trenchwars).
 *
 * Idempotent singleton seeder — creates the RCON worker service user with
 * sentinel `discord_id='SYSTEM_RCON_WORKER'` if absent. Mirrors the Phase 5
 * plan 05-07 BotServiceUserSeeder firstOrCreate idiom exactly so PHPStan +
 * model fillable expectations stay aligned (D-02-04 idiom).
 *
 * The RCON worker service user is the attribution causer for
 * `match_results.source='rcon'` rows written by
 * {@see MatchResultService::upsertFromRcon()}. The user has
 * NO Filament panel access (no `admin-access` permission) and NO roles
 * (T-08-08-03 disposition: accept — system user used purely as causer
 * attribution, no privilege).
 *
 * Sentinel string `SYSTEM_RCON_WORKER` never collides with real OAuth-
 * provisioned users because Discord snowflakes are pure decimal digits.
 *
 * **NOTE on schema:** plan's pseudo-code in <interfaces> referenced `slug`
 * and `is_admin` columns on `users`. Neither exists in the Phase 1 users
 * migration (verified empirically). The fillable here mirrors User::$fillable
 * exactly (discord_id, username, email, locale) — same shape as
 * {@see BotServiceUserSeeder}.
 */
class RconWorkerSystemUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['discord_id' => 'SYSTEM_RCON_WORKER'],
            [
                'username' => 'RCON Worker',
                'email' => 'rcon-worker@system.trenchwars',
                'locale' => 'en',
            ],
        );
    }
}
