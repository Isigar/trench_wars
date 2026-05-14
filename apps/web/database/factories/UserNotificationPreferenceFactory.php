<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * Wave 0 RED stub — real implementation lands in plan 09-02 (migrations) +
 * plan 09-03 (UserNotificationPreference model + User::enabledNotificationChannels).
 *
 * Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
 * Idiom: canonical Phase 4 D-04-01 + Phase 8 plan 08-01 wave 0 (commit 9ea301b).
 *
 * @phpstan-ignore-next-line missingType.generics
 */
class UserNotificationPreferenceFactory extends Factory
{
    /** @phpstan-ignore-next-line property.defaultValue */
    protected $model = 'App\\Models\\UserNotificationPreference';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        throw new RuntimeException(
            'Wave 0 stub — UserNotificationPreferenceFactory will be implemented in plan 09-02 (migration) + 09-03 (model).'
        );
    }
}
