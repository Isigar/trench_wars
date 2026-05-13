<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 03-05 (Wave 3, Seeders).
| Source: .planning/phases/03-games-match-types/03-01-PLAN.md task 1.
| Analog (post-replacement target shape): GameSeeder idempotency tests
| (CONTEXT.md SC-3: fresh seed produces 15 roles + 5 match types; second
|  run after admin edit preserves the edit via firstOrCreate).
|
| RED until plan 03-05 creates `Database\Seeders\GameSeeder`.
| Threat-mitigation T-03-01-01: literal "placeholder" supports phase-close grep audit.
*/

it('placeholder — Wave 0 RED stub replaced by plan 03-05', function (): void {
    expect(class_exists('Database\\Seeders\\GameSeeder'))->toBeTrue();
});
