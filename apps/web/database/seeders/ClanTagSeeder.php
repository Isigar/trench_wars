<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClanTag;
use Illuminate\Database\Seeder;

/**
 * Source: .planning/phases/02-clans-tags/02-04-PLAN.md Task 1.
 *
 * Seeds the starter tag set used by the clan directory filter chips.
 * Idempotent: `firstOrCreate(['slug' => $slug], ...)` uses the UNIQUE slug column
 * as the lookup key — re-running is a no-op.
 *
 * Colors reference the Phase-1 palette tokens:
 *   #2C5282 — blue (EU)
 *   #742A2A — red (NA)
 *   #A4262C — accent/tier (Tier 1)
 */
class ClanTagSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<int, array{slug: string, label: array<string, string>, color: string}> */
        $starters = [
            [
                'slug' => 'eu',
                'label' => ['en' => 'EU'],
                'color' => '#2C5282',
            ],
            [
                'slug' => 'na',
                'label' => ['en' => 'NA'],
                'color' => '#742A2A',
            ],
            [
                'slug' => 'tier-1',
                'label' => ['en' => 'Tier 1'],
                'color' => '#A4262C',
            ],
        ];

        foreach ($starters as $attrs) {
            ClanTag::firstOrCreate(
                ['slug' => $attrs['slug']],
                [
                    'label' => $attrs['label'],
                    'color' => $attrs['color'],
                ]
            );
        }
    }
}
