<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/*
| Wave 0 stub — replaced by plan 07-03 (Wave 2, Models — Article).
| Source: .planning/phases/07-cms/07-01-PLAN.md task 2.
| Analog (canonical Wave 0 idiom): apps/web/database/factories/TournamentFactory.php
| from Phase 6 commit 0b75b8d (which itself follows Phase 3 commit 1d4d736).
|
| Deviation note (Rule 3, blocking issue): the canonical generic
| `@extends Factory<\App\Models\Article>` fails PHPStan L8 against the
| as-yet-uncreated `App\Models\Article` class, and CLAUDE.md §3 forbids
| regenerating phpstan-baseline.neon here. We use per-line `@phpstan-ignore`
| annotations instead — plan 07-03 must remove them when it lands the real
| model + restores the generic docblock.
|
| @phpstan-ignore-next-line missingType.generics
*/
final class ArticleFactory extends Factory
{
    /** @var string */
    protected $model = 'App\\Models\\Article'; // @phpstan-ignore-line property.defaultValue

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }
}
