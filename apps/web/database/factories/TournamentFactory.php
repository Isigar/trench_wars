<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-03-PLAN.md <interfaces> TournamentFactory.
 *
 * Replaces the Wave 0 stub from plan 06-01 (commit aa9d430 path). The string-FQN
 * $model and per-line phpstan-ignore annotations are dropped now that
 * App\Models\Tournament exists — the canonical @extends Factory<Tournament>
 * generic is restored.
 *
 * Default scope: a fresh Game + organiser User per row, format chosen from the
 * 4 LOCKED values (D-011), status defaults to 'draft'. For tests that need a
 * specific tree, use ->for($game) / ->for($organiser, 'organiser') chains.
 *
 * Helper state methods:
 *   - ofFormat('round_robin')      — set a specific format
 *   - inStatus('registering')      — set a specific lifecycle status
 *
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var string $words */
        $words = fake()->unique()->words(3, true);
        $slug = Str::slug($words) . '-' . Str::lower(Str::random(4));

        return [
            'game_id' => Game::factory(),
            'slug' => $slug,
            'title' => ['en' => fake()->sentence(4)],
            'description' => ['en' => fake()->paragraph()],
            'format' => fake()->randomElement([
                'single_elimination',
                'double_elimination',
                'round_robin',
                'swiss',
            ]),
            'status' => 'draft',
            'starts_at' => null,
            'ends_at' => null,
            'max_participants' => null,
            'settings' => null,
            'organiser_user_id' => User::factory(),
            'default_game_match_type_id' => null,
            'is_public' => true,
        ];
    }

    public function ofFormat(string $format): static
    {
        return $this->state(fn (): array => ['format' => $format]);
    }

    public function inStatus(string $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
