<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DiscordGuild;
use Illuminate\Database\Seeder;

/**
 * Source: .planning/phases/02-clans-tags/02-04-PLAN.md Task 1.
 *
 * D-003: discord_guild holds exactly one row for the league's Discord guild.
 * Operational enforcement via seeder (idempotent singleton) + Filament no-Create page (plan 02-13).
 *
 * Singleton trick: `firstOrCreate([])` with an empty $attributes array matches
 * the first existing row of any shape — subsequent invocations are no-ops.
 * Admin fills guild_id/name/icon_url via the Filament edit page after bot setup.
 */
class DiscordGuildSeeder extends Seeder
{
    public function run(): void
    {
        DiscordGuild::firstOrCreate([], [
            'guild_id' => null,
            'name' => null,
            'icon_url' => null,
        ]);
    }
}
