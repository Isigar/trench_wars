<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clan;
use App\Models\ClanInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClanInvite>
 */
class ClanInviteFactory extends Factory
{
    protected $model = ClanInvite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clan_id' => Clan::factory(),
            'invited_user_id' => User::factory(),
            'inviting_user_id' => User::factory(),
            'status' => 'pending',
            'message' => fake()->sentence(),
            'decided_at' => null,
            'expires_at' => null,
        ];
    }
}
