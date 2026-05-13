<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 03-03 (Wave 2, Models).
| Source: .planning/phases/03-games-match-types/03-01-PLAN.md task 1.
| Analog (post-replacement target shape): apps/web/tests/Feature/Models/ClanModelTest.php
|
| This stub asserts the eventual `App\Models\Game` class exists. It is RED
| today because plan 03-03 has not yet created the model. The literal string
| "placeholder" in the it() description supports threat-mitigation T-03-01-01
| (grep audit at phase close detects un-replaced stubs).
*/

it('placeholder — Wave 0 RED stub replaced by plan 03-03', function (): void {
    expect(class_exists('App\\Models\\Game'))->toBeTrue();
});
