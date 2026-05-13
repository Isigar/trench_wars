<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * Wave 0 RED stub — real implementation lands in plan 04-03 (Models + factories + relationships).
 *
 * Source: .planning/phases/04-matches-manual/04-01-PLAN.md task 1
 *  + 04-RESEARCH.md Pattern 1 (six tables) + Pitfall 5 / Assumption A4 (class FQN is
 *    `App\Models\Match`, a legal PHP 8 class identifier despite the lowercase `match` keyword).
 * Analog: apps/web/database/factories/GameFactory.php Wave 0 form (commit 1d4d736).
 *
 * Deviation note (Rule 3, blocking issue): the canonical pattern is
 * `@extends Factory<\App\Models\Match>` + typed `$model`. Both fail PHPStan L8 against
 * the as-yet-uncreated `App\Models\Match` class, and CLAUDE.md §3 forbids regenerating
 * phpstan-baseline.neon here. We use per-line `@phpstan-ignore` annotations instead —
 * plan 04-03 must remove them when it lands the real model and restores the generic docblock.
 *
 * `definition()` throws RuntimeException so any later-wave test that accidentally calls
 * `Match::factory()->create()` before plan 04-03 lands surfaces immediately as a fatal
 * (T-04-01-01 mitigation).
 *
 * @phpstan-ignore-next-line missingType.generics
 */
class MatchFactory extends Factory
{
    /** @phpstan-ignore-next-line property.defaultValue */
    protected $model = 'App\\Models\\Match';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        throw new RuntimeException('MatchFactory definition not yet implemented (Wave 0 stub — replaced by plan 04-03).');
    }
}
