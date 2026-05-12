<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordGuild;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordGuild>
 */
class DiscordGuildFactory extends Factory
{
    protected $model = DiscordGuild::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guild_id' => fake()->numerify('################'),
            'name' => fake()->company(),
            'icon_url' => null,
        ];
    }
}
