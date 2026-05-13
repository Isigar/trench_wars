<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/*
| Wave 0 stub — replaced by plan 06-03 (Wave 2, Models).
| Source: .planning/phases/06-tournaments-brackets/06-01-PLAN.md task 1.
| Analog (canonical Wave 0 idiom): apps/web/database/factories/GameFactory.php
| from Phase 3 commit 1d4d736.
|
| Deviation note (Rule 3, blocking issue): the canonical generic
| `@extends Factory<\App\Models\Tournament>` fails PHPStan L8 against the
| as-yet-uncreated `App\Models\Tournament` class, and CLAUDE.md §3 forbids
| regenerating phpstan-baseline.neon here. We use per-line `@phpstan-ignore`
| annotations instead — plan 06-03 must remove them when it lands the real
| model + restores the generic docblock.
|
| @phpstan-ignore-next-line missingType.generics
*/
final class TournamentFactory extends Factory
{
    /** @phpstan-ignore-next-line property.defaultValue */
    protected $model = 'App\\Models\\Tournament';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }
}
