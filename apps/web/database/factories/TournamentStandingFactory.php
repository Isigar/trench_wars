<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/*
| Wave 0 stub — replaced by plan 06-03 (Wave 2, Models).
| Source: .planning/phases/06-tournaments-brackets/06-01-PLAN.md task 1.
|
| Deviation note (Rule 3): generics omitted until plan 06-03 creates the model;
| PHPStan L8 cannot validate `@extends Factory<App\Models\TournamentStanding>`
| against a non-existent class, and CLAUDE.md §3 forbids baseline regeneration.
|
| @phpstan-ignore-next-line missingType.generics
*/
final class TournamentStandingFactory extends Factory
{
    /** @phpstan-ignore-next-line property.defaultValue */
    protected $model = 'App\\Models\\TournamentStanding';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }
}
