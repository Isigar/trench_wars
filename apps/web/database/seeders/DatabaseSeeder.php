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
            ClanTagSeeder::class,
            // Phase 3 — game catalogue (D-007: HLL preset). MUST run AFTER ClanTagSeeder
            // because Phase 3 migrations land later than Phase 2 (Pitfall 9 — ordering).
            GameSeeder::class,
        ]);
    }
}
