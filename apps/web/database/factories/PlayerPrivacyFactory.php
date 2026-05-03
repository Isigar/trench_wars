<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerPrivacy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerPrivacy>
 */
class PlayerPrivacyFactory extends Factory
{
    protected $model = PlayerPrivacy::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'show_to' => 'community',
            'show_real_name' => false,
            'show_discord_tag' => true,
            'show_clan_history' => true,
            'show_match_history' => true,
            'show_stats' => true,
        ];
    }
}
