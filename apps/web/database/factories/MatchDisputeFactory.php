<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\MatchDispute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 1.
 *
 * Replaces the Wave 0 stub (plan 09-01). Default state yields an open dispute
 * (status='open', no resolution). State methods:
 *   - resolved()  → status='resolved', resolution='no_action',
 *                   resolved_by_user_id + resolved_at set
 *
 * D-04-03-A LOCKED — uses `App\Models\GameMatch` directly. NO aliasing.
 *
 * @extends Factory<MatchDispute>
 */
final class MatchDisputeFactory extends Factory
{
    protected $model = MatchDispute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'raised_by_user_id' => User::factory(),
            'body' => fake()->paragraph(2),
            'status' => 'open',
        ];
    }

    /** Already-resolved dispute — moderator picked `no_action`. */
    public function resolved(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'resolved',
            'resolution' => 'no_action',
            'resolved_by_user_id' => User::factory(),
            'resolved_at' => now(),
        ]);
    }
}
