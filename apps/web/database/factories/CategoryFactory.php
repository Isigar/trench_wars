<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/*
| Wave 0 stub — replaced by plan 07-03 (Wave 2, Models — Category).
| Source: .planning/phases/07-cms/07-01-PLAN.md task 2.
| Analog (canonical Wave 0 idiom): apps/web/database/factories/TournamentFactory.php
| from Phase 6 commit 0b75b8d.
|
| Deviation note (Rule 3, blocking issue): the canonical generic
| `@extends Factory<\App\Models\Category>` fails PHPStan L8 against the
| as-yet-uncreated `App\Models\Category` class. plan 07-03 swaps in the
| real generic + Category::class binding once the model lands.
|
| @phpstan-ignore-next-line missingType.generics
*/
final class CategoryFactory extends Factory
{
    /** @var string */
    protected $model = 'App\\Models\\Category'; // @phpstan-ignore-line property.defaultValue

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }
}
