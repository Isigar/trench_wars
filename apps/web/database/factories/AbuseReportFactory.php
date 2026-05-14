<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AbuseReport;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 1.
 *
 * Replaces the Wave 0 stub (plan 09-01). Default target is a Player (the most
 * common report-abuse target in v1 — clan members reporting harassment by other
 * players). Reason code is randomised across the v1 enum set so tests that
 * filter by reason_code see realistic distributions.
 *
 * target_id is stored as VARCHAR (D-09-02-E) — coerce to string so PHPStan +
 * runtime both see the canonical text type.
 *
 * @extends Factory<AbuseReport>
 */
final class AbuseReportFactory extends Factory
{
    protected $model = AbuseReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_user_id' => User::factory(),
            'target_type' => Player::class,
            'target_id' => fn (): string => (string) Player::factory()->create()->id,
            'reason_code' => fake()->randomElement([
                'harassment',
                'spam',
                'cheating',
                'inappropriate_content',
                'other',
            ]),
            'body' => fake()->sentence(),
            'status' => 'pending',
        ];
    }
}
