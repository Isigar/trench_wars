<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * Wave 0 RED stub — real implementation lands in plan 08-04.
 *
 * Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
 * Analog (canonical Phase 4 D-04-01 idiom): apps/web/database/factories/MatchSlotFactory.php
 * Wave 0 form (commit 6e5024c).
 *
 * match_player_stats is the per-player aggregate rolled up by plan 08-08's
 * MatchPlayerStatAggregator (runs ONCE on match_end — RESEARCH Pitfall 4 anti-pattern
 * is to re-aggregate per event during the match).
 *
 * @phpstan-ignore-next-line missingType.generics
 */
class MatchPlayerStatFactory extends Factory
{
    /** @phpstan-ignore-next-line property.defaultValue */
    protected $model = 'App\\Models\\MatchPlayerStat';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        throw new RuntimeException('MatchPlayerStatFactory definition not yet implemented (Wave 0 stub — replaced by plan 08-04).');
    }
}
