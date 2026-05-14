<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * Wave 0 RED stub — real implementation lands in plan 08-04.
 *
 * Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
 * Analog (canonical Phase 4 D-04-01 idiom): apps/web/database/factories/MatchSlotFactory.php
 * Wave 0 form (commit 6e5024c).
 *
 * match_events is the raw stream from the worker — (id, match_id, event_type,
 * payload JSONB, occurred_at, crcon_stream_id). Plan 08-04 lands the model + real
 * factory; plan 08-07 introduces idempotent upsert keyed by (match_id, crcon_stream_id).
 *
 * @phpstan-ignore-next-line missingType.generics
 */
class MatchEventFactory extends Factory
{
    /** @phpstan-ignore-next-line property.defaultValue */
    protected $model = 'App\\Models\\MatchEvent';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        throw new RuntimeException('MatchEventFactory definition not yet implemented (Wave 0 stub — replaced by plan 08-04).');
    }
}
