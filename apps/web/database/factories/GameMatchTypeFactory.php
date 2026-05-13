<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * Wave 0 stub — replaced by plan 03-03 (Wave 2, Models).
 * Source: .planning/phases/03-games-match-types/03-01-PLAN.md task 1.
 *
 * Deviation note (Rule 3): generics omitted until plan 03-03 creates the model;
 * PHPStan L8 cannot validate `@extends Factory<App\Models\GameMatchType>`
 * against a non-existent class, and CLAUDE.md §3 forbids baseline regeneration.
 *
 * @phpstan-ignore-next-line missingType.generics
 */
class GameMatchTypeFactory extends Factory
{
    /** @phpstan-ignore-next-line property.defaultValue */
    protected $model = 'App\\Models\\GameMatchType';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        throw new RuntimeException('GameMatchTypeFactory definition not yet implemented (Wave 0 stub — replaced by plan 03-03).');
    }
}
