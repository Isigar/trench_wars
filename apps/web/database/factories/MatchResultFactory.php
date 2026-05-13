<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clan;
use App\Models\GameMatch;
use App\Models\MatchResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/04-matches-manual/04-03-PLAN.md <interfaces> MatchResultFactory block.
 *
 * Replaces the Wave 0 stub (commit 6e5024c). Default scope: a fresh GameMatch + a fresh
 * winner Clan + a fresh recorder User per row. Scores default to a realistic 4-1 finish.
 *
 * @extends Factory<MatchResult>
 */
class MatchResultFactory extends Factory
{
    protected $model = MatchResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'winner_clan_id' => Clan::factory(),
            'allies_score' => 4,
            'axis_score' => 1,
            'notes' => null,
            'recorded_by_user_id' => User::factory(),
            'recorded_at' => now(),
        ];
    }
}
