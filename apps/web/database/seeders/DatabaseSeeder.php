<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            DiscordGuildSeeder::class,
            // Phase 5 plan 05-07 task 2 — bot service user singleton. Must run BEFORE any
            // seeders that reference role-sync attribution (none currently; reserved for
            // Phase 9 reactivation flows). Discord guild + bot user are paired singletons.
            BotServiceUserSeeder::class,
            ClanTagSeeder::class,
            // Phase 3 — game catalogue (D-007: HLL preset). MUST run AFTER ClanTagSeeder
            // because Phase 3 migrations land later than Phase 2 (Pitfall 9 — ordering).
            GameSeeder::class,
            // Phase 7 — CMS editorial taxonomy (4 starter categories LOCKED via
            // Open Question 3 of plan 07-03). MUST run AFTER all Phase 1-6 seeders
            // because Phase 7 migrations land in Wave 1 (timestamp 2026_05_15_1200xx)
            // after every prior phase (Pitfall 9 — ordering).
            CategorySeeder::class,
        ]);
    }
}
