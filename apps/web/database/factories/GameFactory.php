<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * Wave 0 stub — replaced by plan 03-03 (Wave 2, Models).
 * Source: .planning/phases/03-games-match-types/03-01-PLAN.md task 1.
 * Analog: apps/web/database/factories/ClanFactory.php (current GREEN form).
 *
 * Deviation note (Rule 3, blocking issue): the plan called for an
 * `@extends Factory<\App\Models\Game>` PHPDoc generic plus a typed `$model`.
 * Both fail PHPStan L8 against the as-yet-uncreated `App\Models\Game` class,
 * and CLAUDE.md §3 forbids regenerating phpstan-baseline.neon here. We use
 * per-line `@phpstan-ignore` annotations instead — plan 03-03 must remove
 * them when it lands the real model + restores the generic docblock.
 *
 * @phpstan-ignore-next-line missingType.generics
 */
class GameFactory extends Factory
{
    /** @phpstan-ignore-next-line property.defaultValue */
    protected $model = 'App\\Models\\Game';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        throw new RuntimeException('GameFactory definition not yet implemented (Wave 0 stub — replaced by plan 03-03).');
    }
}
