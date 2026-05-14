<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * Wave 0 RED stub — real implementation lands in plan 09-02 (migrations) +
 * plan 09-07 (Ban model + BanService).
 *
 * Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
 * Idiom: canonical Phase 4 D-04-01 + Phase 8 plan 08-01 wave 0 (commit 9ea301b).
 * String FQN $model + per-line @phpstan-ignore for missingType.generics +
 * property.defaultValue; definition() throws RuntimeException so accidental
 * ::factory() calls fail loud instead of silently inserting empty rows.
 *
 * @phpstan-ignore-next-line missingType.generics
 */
class BanFactory extends Factory
{
    /** @phpstan-ignore-next-line property.defaultValue */
    protected $model = 'App\\Models\\Ban';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        throw new RuntimeException(
            'Wave 0 stub — BanFactory will be implemented in plan 09-02 (migration) + 09-07 (model).'
        );
    }
}
