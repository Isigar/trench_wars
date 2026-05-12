<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClanApplication>
 */
class ClanApplicationFactory extends Factory
{
    protected $model = ClanApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clan_id' => Clan::factory(),
            'applicant_user_id' => User::factory(),
            'status' => 'pending',
            'message' => fake()->paragraph(),
            'decided_at' => null,
            'decided_by' => null,
        ];
    }
}
