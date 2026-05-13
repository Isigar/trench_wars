<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use App\Models\GameRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Source: .planning/phases/03-games-match-types/03-05-PLAN.md Task 1.
 *
 * HLL preset seeder (D-007: HLL is a SEEDED preset, not hardcoded). All inserts use
 * firstOrCreate([lookup_keys], [other_attrs]) where lookup_keys match the table UNIQUE
 * index (Pitfall 5):
 *
 *   - games.key UNIQUE              → ['key' => 'hll']
 *   - game_roles (game_id, key)     → ['game_id', 'key']
 *   - game_match_types (game_id, key) → ['game_id', 'key']
 *   - gmtrl (game_match_type_id, game_role_id) → composite
 *
 * The [other_attrs] argument fires ONLY on create — admin edits to translatable
 * `name`, `display_name`, or `capacity` survive `db:seed` re-runs (Pattern 5).
 *
 * Capacity matrix:
 *   - Scrim 50v50: 15 rows, 50 total slots — full HLL company shape (RESEARCH Q2 RESOLVED).
 *   - Skirmish 6v6: 5 rows, 6 total infantry slots (no armour).
 *   - Friendly, Tournament, Clan War: ZERO capacity rows seeded — admin fills via the
 *     Filament Edit page (RESEARCH Q2 RESOLVED, Recommendation B).
 *
 * Cross-game invariant (Pitfall 10): all role + matchType lookups are scoped to $hll, so
 * no off-game rows can leak into seedRoleLimits(). The plan 03-03 saving() listener is
 * the model-layer safety net for API/Console writes.
 */
class GameSeeder extends Seeder
{
    public function run(): void
    {
        $hll = Game::firstOrCreate(
            ['key' => 'hll'],
            [
                'name' => ['en' => 'Hell Let Loose'],
                'is_active' => true,
            ]
        );

        $this->seedRoles($hll);
        $matchTypeIds = $this->seedMatchTypes($hll);
        $this->seedRoleLimits($hll, $matchTypeIds);
    }

    private function seedRoles(Game $hll): void
    {
        /** @var list<array{key: string, display: string, sort: int}> $roles */
        $roles = [
            ['key' => 'commander', 'display' => 'Commander', 'sort' => 10],
            ['key' => 'officer', 'display' => 'Officer', 'sort' => 20],
            ['key' => 'squad_leader', 'display' => 'Squad Leader', 'sort' => 30],
            ['key' => 'rifleman', 'display' => 'Rifleman', 'sort' => 40],
            ['key' => 'assault', 'display' => 'Assault', 'sort' => 50],
            ['key' => 'automatic_rifleman', 'display' => 'Automatic Rifleman', 'sort' => 60],
            ['key' => 'medic', 'display' => 'Medic', 'sort' => 70],
            ['key' => 'engineer', 'display' => 'Engineer', 'sort' => 80],
            ['key' => 'support', 'display' => 'Support', 'sort' => 90],
            ['key' => 'heavy_machine_gunner', 'display' => 'Heavy Machine Gunner', 'sort' => 100],
            ['key' => 'anti_tank', 'display' => 'Anti-Tank', 'sort' => 110],
            ['key' => 'sniper', 'display' => 'Sniper', 'sort' => 120],
            ['key' => 'spotter', 'display' => 'Spotter', 'sort' => 130],
            ['key' => 'tank_commander', 'display' => 'Tank Commander', 'sort' => 140],
            ['key' => 'crewman', 'display' => 'Crewman', 'sort' => 150],
        ];

        foreach ($roles as $r) {
            GameRole::firstOrCreate(
                ['game_id' => $hll->id, 'key' => $r['key']],
                [
                    'display_name' => ['en' => $r['display']],
                    'sort_order' => $r['sort'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @return array<string, string> map of match-type key → match-type UUID
     */
    private function seedMatchTypes(Game $hll): array
    {
        /** @var list<array{key: string, name: string, description: string}> $matchTypes */
        $matchTypes = [
            [
                'key' => 'scrim_50v50',
                'name' => 'Scrim 50v50',
                'description' => 'Full-company practice match (50 vs 50). Standard HLL company shape with infantry + armour + recon.',
            ],
            [
                'key' => 'skirmish_6v6',
                'name' => 'Skirmish 6v6',
                'description' => 'Small-format infantry-only skirmish (6 vs 6). One squad per side, no armour.',
            ],
            [
                'key' => 'friendly',
                'name' => 'Friendly',
                'description' => 'Casual friendly match. Admin configures the role-slot capacity per event via the Filament panel.',
            ],
            [
                'key' => 'tournament',
                'name' => 'Tournament',
                'description' => 'Competitive tournament fixture. Capacity is set by the tournament organiser via the admin panel.',
            ],
            [
                'key' => 'clan_war',
                'name' => 'Clan War',
                'description' => 'Ranked clan-vs-clan war match. Admin sets the capacity matrix per fixture via the Filament panel.',
            ],
        ];

        /** @var array<string, string> $matchTypeIds */
        $matchTypeIds = [];

        foreach ($matchTypes as $m) {
            $row = GameMatchType::firstOrCreate(
                ['game_id' => $hll->id, 'key' => $m['key']],
                [
                    'name' => ['en' => $m['name']],
                    'description' => ['en' => $m['description']],
                    'is_active' => true,
                ]
            );

            $matchTypeIds[$m['key']] = $row->id;
        }

        return $matchTypeIds;
    }

    /**
     * Seed capacity rows for Scrim 50v50 (50 slots across 15 roles) and Skirmish 6v6
     * (6 slots across 5 infantry roles). Friendly / Tournament / Clan War are admin-fillable
     * blanks per RESEARCH Q2 RESOLVED.
     *
     * @param  array<string, string>  $matchTypeIds
     */
    private function seedRoleLimits(Game $hll, array $matchTypeIds): void
    {
        /** @var Collection<int, GameRole> $rolesCollection */
        $rolesCollection = $hll->roles()->get();
        /** @var array<string, GameRole> $roles */
        $roles = $rolesCollection->keyBy('key')->all();

        /**
         * Scrim 50v50 capacity distribution (50 total):
         *   commander 1 + officer 4 + squad_leader 4 + rifleman 14 + assault 4
         *   + automatic_rifleman 4 + medic 4 + engineer 4 + support 4
         *   + heavy_machine_gunner 2 + anti_tank 2 + sniper 1 + spotter 1
         *   + tank_commander 1 + crewman 0 = 50.
         *
         * @var array<string, int> $scrim50v50
         */
        $scrim50v50 = [
            'commander' => 1,
            'officer' => 4,
            'squad_leader' => 4,
            'rifleman' => 14,
            'assault' => 4,
            'automatic_rifleman' => 4,
            'medic' => 4,
            'engineer' => 4,
            'support' => 4,
            'heavy_machine_gunner' => 2,
            'anti_tank' => 2,
            'sniper' => 1,
            'spotter' => 1,
            'tank_commander' => 1,
            'crewman' => 0,
        ];

        foreach ($scrim50v50 as $roleKey => $capacity) {
            GameMatchTypeRoleLimit::firstOrCreate(
                [
                    'game_match_type_id' => $matchTypeIds['scrim_50v50'],
                    'game_role_id' => $roles[$roleKey]->id,
                ],
                [
                    'capacity' => $capacity,
                    'sort_order' => $roles[$roleKey]->sort_order,
                ]
            );
        }

        /**
         * Skirmish 6v6 capacity distribution (6 total, infantry only):
         *   squad_leader 1 + rifleman 2 + assault 1 + medic 1 + support 1 = 6.
         *
         * @var array<string, int> $skirmish6v6
         */
        $skirmish6v6 = [
            'squad_leader' => 1,
            'rifleman' => 2,
            'assault' => 1,
            'medic' => 1,
            'support' => 1,
        ];

        foreach ($skirmish6v6 as $roleKey => $capacity) {
            GameMatchTypeRoleLimit::firstOrCreate(
                [
                    'game_match_type_id' => $matchTypeIds['skirmish_6v6'],
                    'game_role_id' => $roles[$roleKey]->id,
                ],
                [
                    'capacity' => $capacity,
                    'sort_order' => $roles[$roleKey]->sort_order,
                ]
            );
        }

        // Friendly / Tournament / Clan War: no capacity rows seeded (RESEARCH Q2 RESOLVED).
        // Admin fills via Filament Edit page after seed.
    }
}
