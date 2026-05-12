<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * Wave 0 stub — plan 02-04 (Wave 1, Seeders) replaces this with the real definition.
 * Source analog: apps/web/database/factories/PlayerFactory.php.
 *
 * String form of $model so PHP does not eager-load App\Models\DiscordGuild before Wave 1 ships.
 * Wave 1 replaces $model with DiscordGuild::class once the model exists.
 *
 * @extends Factory<Model>
 */
final class DiscordGuildFactory extends Factory
{
    /** @phpstan-ignore-next-line */
    protected $model = 'App\\Models\\DiscordGuild';

    /** @return array<string, mixed> */
    public function definition(): array
    {
        throw new \RuntimeException('Wave 0 stub - plan 02-03 (Models) replaces this with real definitions.');
    }
}
