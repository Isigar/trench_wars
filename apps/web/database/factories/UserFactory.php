<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Discord snowflakes are 17–19 digit decimals; pick 18 to match real-world IDs.
            'discord_id' => (string) fake()->unique()->numerify('##################'),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'avatar_url' => null,
            'locale' => 'en',
        ];
    }
}
