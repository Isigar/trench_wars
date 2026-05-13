<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 03-08 (Wave 6, Admin presence + 403 gate).
| Source: .planning/phases/03-games-match-types/03-01-PLAN.md task 1.
| Analog (post-replacement target shape): apps/web/tests/Feature/Admin/ClanResourcesPresentTest.php
|
| The replacement test (plan 03-08) will register the admin user with
| `admin-access` permission and assert GET /admin/games + /admin/game-match-types
| return 200 for admins, 403 for non-admins. The `beforeEach` block below is
| structurally identical to the Phase 2 analog so plan 03-08 only needs to
| add `it()` blocks, not greenfield setup.
|
| RED until plan 03-06 + 03-07 register the Filament Resources.
| Threat-mitigation T-03-01-01: literal "placeholder" supports phase-close grep audit.
*/

use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);
});

it('placeholder — Wave 0 RED stub replaced by plan 03-08', function (): void {
    // Plan 03-06 registers GameResource at /admin/games; until then this class is missing.
    expect(class_exists('App\\Filament\\Resources\\GameResource'))->toBeTrue();
});
