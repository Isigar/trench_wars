<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * Wave 0 RED stub — real implementation lands in plan 08-03.
 *
 * Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
 * Analog (canonical Phase 4 D-04-01 idiom): apps/web/database/factories/MatchSlotFactory.php
 * Wave 0 form (commit 6e5024c).
 *
 * Deviation note (Rule 3, blocking issue): the canonical generic
 * `@extends Factory<\App\Models\MatchServer>` fails PHPStan L8 against the
 * as-yet-uncreated `App\Models\MatchServer` class — plan 08-02 lands the
 * migration and plan 08-03 lands the model + generic. CLAUDE.md §3 forbids
 * regenerating phpstan-baseline.neon, so per-line @phpstan-ignore is the
 * Wave 0 escape hatch.
 *
 * @phpstan-ignore-next-line missingType.generics
 */
class MatchServerFactory extends Factory
{
    /** @phpstan-ignore-next-line property.defaultValue */
    protected $model = 'App\\Models\\MatchServer';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        throw new RuntimeException('MatchServerFactory definition not yet implemented (Wave 0 stub — replaced by plan 08-03).');
    }
}
