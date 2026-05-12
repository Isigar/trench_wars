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
            // Phase 3+ adds GameSeeder etc.
        ]);
    }
}
