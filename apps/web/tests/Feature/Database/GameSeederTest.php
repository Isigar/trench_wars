<?php

declare(strict_types=1);

/*
| Plan 03-05 Task 2 — replaces the Wave 0 RED stub from plan 03-01.
|
| Asserts the four contracts of CONTEXT.md SC-3:
|   1. First run creates exactly 1 Game + 15 Roles + 5 MatchTypes + 20 RoleLimits.
|   2. Re-running the seeder leaves all row counts unchanged (firstOrCreate idempotency, Pitfall 5).
|   3. Admin edits to translatable `name` survive a re-seed (firstOrCreate's [other_attrs]
|      argument fires on create only — Pattern 5 contract).
|   4. Admin edits to capacity on a RoleLimit row survive a re-seed (same mechanism).
|   5. Capacity rows are only seeded for Scrim 50v50 + Skirmish 6v6 (RESEARCH Q2 RESOLVED).
|   6. Every seeded role is scoped to the HLL game (no cross-game leakage; T-03-05-04 sentinel).
|
| RefreshDatabase auto-applied via tests/Pest.php uses(...)->in('Feature').
*/

use App\Models\Game;
use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use App\Models\GameRole;
use Database\Seeders\GameSeeder;

it('seeds exactly 1 Game (HLL), 15 GameRoles, 5 GameMatchTypes, and 20 RoleLimits', function (): void {
    $this->seed(GameSeeder::class);

    $hll = Game::where('key', 'hll')->first();

    expect(Game::count())->toBe(1);
    expect($hll)->not->toBeNull();
    expect($hll->key)->toBe('hll');
    expect($hll->getTranslations('name'))->toBe(['en' => 'Hell Let Loose']);
    expect($hll->is_active)->toBeTrue();

    expect(GameRole::where('game_id', $hll->id)->count())->toBe(15);
    expect(GameMatchType::where('game_id', $hll->id)->count())->toBe(5);
    expect(GameMatchTypeRoleLimit::count())->toBe(20);
});

it('is idempotent — running the seeder twice does not duplicate any row', function (): void {
    $this->seed(GameSeeder::class);
    $this->seed(GameSeeder::class);

    $hll = Game::where('key', 'hll')->first();

    expect(Game::count())->toBe(1);
    expect(GameRole::where('game_id', $hll->id)->count())->toBe(15);
    expect(GameMatchType::where('game_id', $hll->id)->count())->toBe(5);
    expect(GameMatchTypeRoleLimit::count())->toBe(20);
});

it('preserves admin edits to translatable Game name on re-seed (firstOrCreate other-attrs fire only on create)', function (): void {
    $this->seed(GameSeeder::class);

    $hll = Game::where('key', 'hll')->first();
    $hll->setTranslation('name', 'en', 'My Custom HLL Name');
    $hll->save();

    $this->seed(GameSeeder::class);

    $reloaded = Game::where('key', 'hll')->first();
    expect($reloaded->getTranslations('name'))->toBe(['en' => 'My Custom HLL Name']);
    expect($reloaded->getTranslations('name'))->not->toBe(['en' => 'Hell Let Loose']);
});

it('preserves admin edits to translatable GameRole display_name on re-seed', function (): void {
    $this->seed(GameSeeder::class);

    $hll = Game::where('key', 'hll')->first();
    $rifleman = GameRole::where('game_id', $hll->id)->where('key', 'rifleman')->first();
    $rifleman->setTranslation('display_name', 'en', 'Custom Rifleman Label');
    $rifleman->save();

    $this->seed(GameSeeder::class);

    $reloaded = GameRole::where('game_id', $hll->id)->where('key', 'rifleman')->first();
    expect($reloaded->getTranslations('display_name'))->toBe(['en' => 'Custom Rifleman Label']);
});

it('preserves admin capacity edits on RoleLimit rows after re-seed', function (): void {
    $this->seed(GameSeeder::class);

    $hll = Game::where('key', 'hll')->first();
    $scrim = GameMatchType::where('game_id', $hll->id)->where('key', 'scrim_50v50')->first();
    $rifleman = GameRole::where('game_id', $hll->id)->where('key', 'rifleman')->first();

    $row = GameMatchTypeRoleLimit::where('game_match_type_id', $scrim->id)
        ->where('game_role_id', $rifleman->id)
        ->first();

    expect($row)->not->toBeNull();
    expect($row->capacity)->toBe(14); // seeded default

    $row->capacity = 99;
    $row->save();

    $this->seed(GameSeeder::class);

    $reloaded = GameMatchTypeRoleLimit::find($row->id);
    expect($reloaded->capacity)->toBe(99);
});

it('seeds capacity rows only for Scrim 50v50 and Skirmish 6v6 — Friendly, Tournament, Clan War are admin-fillable blanks', function (): void {
    $this->seed(GameSeeder::class);

    $hll = Game::where('key', 'hll')->first();

    $scrimId = GameMatchType::where('game_id', $hll->id)->where('key', 'scrim_50v50')->value('id');
    $skirmishId = GameMatchType::where('game_id', $hll->id)->where('key', 'skirmish_6v6')->value('id');
    $friendlyId = GameMatchType::where('game_id', $hll->id)->where('key', 'friendly')->value('id');
    $tournamentId = GameMatchType::where('game_id', $hll->id)->where('key', 'tournament')->value('id');
    $clanWarId = GameMatchType::where('game_id', $hll->id)->where('key', 'clan_war')->value('id');

    expect(GameMatchTypeRoleLimit::where('game_match_type_id', $scrimId)->count())->toBe(15);
    expect(GameMatchTypeRoleLimit::where('game_match_type_id', $skirmishId)->count())->toBe(5);
    expect(GameMatchTypeRoleLimit::where('game_match_type_id', $friendlyId)->count())->toBe(0);
    expect(GameMatchTypeRoleLimit::where('game_match_type_id', $tournamentId)->count())->toBe(0);
    expect(GameMatchTypeRoleLimit::where('game_match_type_id', $clanWarId)->count())->toBe(0);
});

it('seeds the Scrim 50v50 capacity distribution summing to exactly 50 slots', function (): void {
    $this->seed(GameSeeder::class);

    $hll = Game::where('key', 'hll')->first();
    $scrimId = GameMatchType::where('game_id', $hll->id)->where('key', 'scrim_50v50')->value('id');

    $total = (int) GameMatchTypeRoleLimit::where('game_match_type_id', $scrimId)->sum('capacity');
    expect($total)->toBe(50);
});

it('seeds the Skirmish 6v6 capacity distribution summing to exactly 6 slots', function (): void {
    $this->seed(GameSeeder::class);

    $hll = Game::where('key', 'hll')->first();
    $skirmishId = GameMatchType::where('game_id', $hll->id)->where('key', 'skirmish_6v6')->value('id');

    $total = (int) GameMatchTypeRoleLimit::where('game_match_type_id', $skirmishId)->sum('capacity');
    expect($total)->toBe(6);
});

it('seeds the canonical 15-role HLL roster with the correct keys', function (): void {
    $this->seed(GameSeeder::class);

    $hll = Game::where('key', 'hll')->first();
    $keys = GameRole::where('game_id', $hll->id)->orderBy('sort_order')->pluck('key')->all();

    expect($keys)->toBe([
        'commander',
        'officer',
        'squad_leader',
        'rifleman',
        'assault',
        'automatic_rifleman',
        'medic',
        'engineer',
        'support',
        'heavy_machine_gunner',
        'anti_tank',
        'sniper',
        'spotter',
        'tank_commander',
        'crewman',
    ]);
});

it('all seeded roles are scoped to HLL (no cross-game role injection)', function (): void {
    $this->seed(GameSeeder::class);

    $hll = Game::where('key', 'hll')->first();

    expect(GameRole::where('game_id', '!=', $hll->id)->count())->toBe(0);
    expect(GameMatchType::where('game_id', '!=', $hll->id)->count())->toBe(0);
});
