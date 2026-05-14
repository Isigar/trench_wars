<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Source: .planning/phases/07-cms/07-03-PLAN.md task 2(a).
 *
 * Seeds the 4 starter categories LOCKED inline by Open Question 3 of the
 * Phase 7 plan and recommended in .planning/phases/07-cms/07-RESEARCH.md
 * § Open Question 3. The set is deliberately tight (4 rows) because per-row
 * category management lives in Filament (plan 07-11); the seed only guarantees
 * the editorial taxonomy is non-empty on first deploy.
 *
 * Order = display order: News (default landing) → Match Reports → Tournament
 * Updates → Community.
 *
 * Idempotent: `firstOrCreate(['slug' => Str::slug($name)], ...)` uses the
 * UNIQUE slug column as the lookup key — re-running is a no-op. Matches the
 * Phase 2 plan 02-04 ClanTagSeeder precedent.
 *
 * Registered AFTER GameSeeder in DatabaseSeeder::run() — Phase 7 migrations
 * land in Wave 1 (timestamp 2026_05_15_1200xx) after Phase 3's game seeder
 * dependencies, so the categories table exists by the time this seeder fires.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<string> $starters */
        $starters = [
            'News',
            'Match Reports',
            'Tournament Updates',
            'Community',
        ];

        foreach ($starters as $name) {
            Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => ['en' => $name]],
            );
        }
    }
}
