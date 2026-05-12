<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClanMembership>
 */
class ClanMembershipFactory extends Factory
{
    protected $model = ClanMembership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clan_id' => Clan::factory(),
            'user_id' => User::factory(),
            'role' => 'member',
            'joined_at' => now(),
            'left_at' => null,
            'invited_by' => null,
        ];
    }
}
