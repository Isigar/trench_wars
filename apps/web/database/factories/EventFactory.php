<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\GameMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/04-matches-manual/04-03-PLAN.md <interfaces> EventFactory block.
 *
 * Replaces the Wave 0 stub (commit 6e5024c). Factory default creates a STANDALONE Event
 * with a fresh GameMatch as the polymorphic owner. Real production writes go through
 * MatchObserver (plan 04-08), not Event::factory.
 *
 * `eventable_type` is set to `GameMatch::class` (FQN string 'App\\Models\\GameMatch') —
 * Eloquent stores the FQN by default since no morphMap is configured. Tests that need to
 * verify polymorphic round-trip should compare against `GameMatch::class`.
 *
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'eventable_type' => GameMatch::class,
            'eventable_id' => GameMatch::factory(),
            'starts_at' => now()->addDay(),
            'ends_at' => null,
            'title' => ['en' => fake()->sentence(2)],
            'is_public' => true,
        ];
    }
}
