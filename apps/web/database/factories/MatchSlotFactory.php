<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * Wave 0 RED stub — real implementation lands in plan 04-03.
 *
 * Source: .planning/phases/04-matches-manual/04-01-PLAN.md task 1.
 * Analog: apps/web/database/factories/GameFactory.php Wave 0 form (commit 1d4d736).
 * See MatchFactory header for the @phpstan-ignore rationale.
 *
 * @phpstan-ignore-next-line missingType.generics
 */
class MatchSlotFactory extends Factory
{
    /** @phpstan-ignore-next-line property.defaultValue */
    protected $model = 'App\\Models\\MatchSlot';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        throw new RuntimeException('MatchSlotFactory definition not yet implemented (Wave 0 stub — replaced by plan 04-03).');
    }
}
