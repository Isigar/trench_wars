<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * Wave 0 RED stub — real implementation lands in plan 08-03.
 *
 * Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
 * Analog (canonical Phase 4 D-04-01 idiom): apps/web/database/factories/MatchSlotFactory.php
 * Wave 0 form (commit 6e5024c).
 *
 * Plan 08-02 lands the migration with the EXCLUDE constraint on overlapping
 * (server_id, reserved_from..reserved_to) ranges (RESEARCH § match_server_bookings).
 * Plan 08-03 swaps this stub for the real factory with default
 * `reserved_from = scheduled_start − 5 minutes`, `reserved_to = scheduled_end + 30 minutes`.
 *
 * @phpstan-ignore-next-line missingType.generics
 */
class MatchServerBookingFactory extends Factory
{
    /** @phpstan-ignore-next-line property.defaultValue */
    protected $model = 'App\\Models\\MatchServerBooking';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        throw new RuntimeException('MatchServerBookingFactory definition not yet implemented (Wave 0 stub — replaced by plan 08-03).');
    }
}
