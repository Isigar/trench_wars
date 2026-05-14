<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ban;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 1.
 *
 * Replaces the Wave 0 stub (plan 09-01). Default state yields a temporary,
 * unlifted ban expiring in 7 days. State methods cover the three real-world
 * configurations the suite + dispatcher need:
 *   - permanent()  → expires_at=null, ban_type='permanent'
 *   - lifted()     → lifted_at set, lifted_by_user_id set, lift_reason set
 *
 * @extends Factory<Ban>
 */
final class BanFactory extends Factory
{
    protected $model = Ban::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ban_type' => 'temporary',
            'reason' => fake()->sentence(8),
            'expires_at' => now()->addDays(7),
            'issued_by_user_id' => User::factory(),
        ];
    }

    /** Permanent ban — no expiry. */
    public function permanent(): self
    {
        return $this->state(fn (array $attributes): array => [
            'ban_type' => 'permanent',
            'expires_at' => null,
        ]);
    }

    /** Already-lifted ban — used by middleware tests asserting "not active". */
    public function lifted(): self
    {
        return $this->state(fn (array $attributes): array => [
            'lifted_at' => now()->subHour(),
            'lifted_by_user_id' => User::factory(),
            'lift_reason' => fake()->sentence(),
        ]);
    }
}
