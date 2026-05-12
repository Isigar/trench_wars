<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Clan>
 */
class ClanFactory extends Factory
{
    protected $model = Clan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'owner_user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'tag' => strtoupper(Str::random(3)),
            'description' => ['en' => fake()->sentence()],
            'country_code' => fake()->countryCode(),
            'status' => 'active',
        ];
    }
}
