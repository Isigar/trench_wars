<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 03-03 (Wave 2, Models).
| Source: .planning/phases/03-games-match-types/03-01-PLAN.md task 1.
| Analog (post-replacement target shape): apps/web/tests/Feature/Models/ClanModelTest.php
|
| RED until plan 03-03 creates `App\Models\GameMatchType`.
| Threat-mitigation T-03-01-01: literal "placeholder" supports phase-close grep audit.
*/

it('placeholder — Wave 0 RED stub replaced by plan 03-03', function (): void {
    expect(class_exists('App\\Models\\GameMatchType'))->toBeTrue();
});
