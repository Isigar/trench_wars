<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClanTag;
use App\Models\GameMatch;
use App\Models\MatchAccessRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/04-matches-manual/04-03-PLAN.md <interfaces> MatchAccessRuleFactory block.
 *
 * Replaces the Wave 0 stub (commit 6e5024c). Default scope: a fresh GameMatch + a fresh
 * ClanTag per row.
 *
 * @extends Factory<MatchAccessRule>
 */
class MatchAccessRuleFactory extends Factory
{
    protected $model = MatchAccessRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'clan_tag_id' => ClanTag::factory(),
        ];
    }
}
