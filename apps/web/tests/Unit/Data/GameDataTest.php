<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 03-04 (Wave 2, DTOs + TS regen).
| Source: .planning/phases/03-games-match-types/03-01-PLAN.md task 1.
| Analog (post-replacement target shape): apps/web/tests/Unit/Data/PlayerProfileDataTest.php
|
| Plan 03-04 introduces `App\Data\GameData` (spatie/laravel-data) plus sibling
| GameRoleData, GameMatchTypeData, GameMatchTypeRoleLimitData DTOs and emits
| TypeScript types via spatie/laravel-typescript-transformer.
|
| RED until plan 03-04 creates `App\Data\GameData`.
| Threat-mitigation T-03-01-01: literal "placeholder" supports phase-close grep audit.
*/

it('placeholder — Wave 0 RED stub replaced by plan 03-04', function (): void {
    expect(class_exists('App\\Data\\GameData'))->toBeTrue();
});
