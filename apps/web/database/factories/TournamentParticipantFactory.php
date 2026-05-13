<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clan;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-03-PLAN.md <interfaces> TournamentParticipantFactory.
 *
 * Replaces the Wave 0 stub. Default scope spawns a fresh Tournament + Clan per
 * row. For tests that need a single tree, use ->for($tournament)->for($clan)
 * chains.
 *
 * Helper state methods:
 *   - active()        — flip status from 'registered' to 'active'
 *   - withSeed(int)   — set a specific seed value
 *
 * @extends Factory<TournamentParticipant>
 */
class TournamentParticipantFactory extends Factory
{
    protected $model = TournamentParticipant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'clan_id' => Clan::factory(),
            'seed' => null,
            'status' => 'registered',
            'placement' => null,
            'registered_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => 'active']);
    }

    public function withSeed(int $seed): static
    {
        return $this->state(fn (): array => ['seed' => $seed]);
    }
}
