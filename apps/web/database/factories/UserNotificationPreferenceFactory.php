<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 1.
 *
 * Replaces the Wave 0 stub (plan 09-01). Default state yields an explicit
 * "database channel enabled for match_starting_soon" row — the simplest
 * positive case. State methods cover the dispatch matrix the suite needs:
 *   - discord()    → channel='discord'
 *   - disabled()   → enabled=false (opt-out)
 *   - forEvent($e) → override event_type
 *
 * @extends Factory<UserNotificationPreference>
 */
final class UserNotificationPreferenceFactory extends Factory
{
    protected $model = UserNotificationPreference::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_type' => 'match_starting_soon',
            'channel' => 'database',
            'enabled' => true,
        ];
    }

    /** Switch the channel to discord. */
    public function discord(): self
    {
        return $this->state(fn (array $attributes): array => [
            'channel' => 'discord',
        ]);
    }

    /** Explicit opt-out (overrides the default-ON policy). */
    public function disabled(): self
    {
        return $this->state(fn (array $attributes): array => [
            'enabled' => false,
        ]);
    }

    /** Pin to a specific event_type. */
    public function forEvent(string $eventType): self
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => $eventType,
        ]);
    }
}
