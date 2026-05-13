<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * Wave 0 RED stub — real implementation lands in plan 05-02.
 *
 * Source: .planning/phases/05-discord-bot-v1/05-01-PLAN.md task 2.
 * Idiom: Phase 4 04-01 factory stubs (commit 6e5024c) — string FQN $model + per-line
 *
 * @phpstan-ignore comments; CLAUDE.md §3 forbids regenerating phpstan-baseline.neon so
 * inline ignores are the only path. Plan 05-02 MUST remove the ignores when
 * App\Models\DiscordOutboundMessage lands.
 *
 * @phpstan-ignore-next-line missingType.generics
 */
class DiscordOutboundMessageFactory extends Factory
{
    /** @phpstan-ignore-next-line property.defaultValue */
    protected $model = 'App\\Models\\DiscordOutboundMessage';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        throw new RuntimeException('DiscordOutboundMessageFactory definition not yet implemented (Wave 0 stub — replaced by plan 05-02).');
    }
}
