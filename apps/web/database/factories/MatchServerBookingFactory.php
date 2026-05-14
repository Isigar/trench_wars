<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\MatchServer;
use App\Models\MatchServerBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/08-rcon-automation/08-03-PLAN.md task 1.
 *
 * Replaces the Wave 0 stub (plan 08-01). Default window: `now() + 1h` .. `now() + 3h`.
 * Use `forMatch()` + `onServer()` state helpers to pin FKs without invoking the
 * default parent factories (the EXCLUDE-overlap tests demand explicit parents
 * to control which bookings collide). State helper `overlapping()` mirrors the
 * window of another booking to deliberately trigger the EXCLUDE constraint in
 * tests.
 *
 * @extends Factory<MatchServerBooking>
 */
class MatchServerBookingFactory extends Factory
{
    protected $model = MatchServerBooking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'server_id' => MatchServer::factory(),
            'reserved_from' => now()->addHour(),
            'reserved_to' => now()->addHours(3),
            'status' => 'active',
        ];
    }

    /**
     * Pin the booking to a specific match (skips the default GameMatch::factory()).
     */
    public function forMatch(GameMatch $match): self
    {
        return $this->state(fn (array $attributes): array => [
            'match_id' => $match->id,
        ]);
    }

    /**
     * Pin the booking to a specific server (skips the default MatchServer::factory()).
     */
    public function onServer(MatchServer $server): self
    {
        return $this->state(fn (array $attributes): array => [
            'server_id' => $server->id,
        ]);
    }

    /**
     * Mirror another booking's window — used to deliberately trigger the
     * `match_server_bookings_no_overlap` EXCLUDE constraint in tests.
     */
    public function overlapping(MatchServerBooking $other): self
    {
        return $this->state(fn (array $attributes): array => [
            'reserved_from' => $other->reserved_from,
            'reserved_to' => $other->reserved_to,
        ]);
    }
}
