<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Source: .planning/phases/05-discord-bot-v1/05-07-PLAN.md task 2
 * + 05-RESEARCH.md Open Question Q4 (bot service user provisioning).
 *
 * Idempotent singleton seeder — creates the bot service user with sentinel
 * `discord_id='SYSTEM_BOT'` if absent. Mirrors the Phase 2 plan 02-04
 * DiscordGuildSeeder firstOrCreate idiom.
 *
 * The bot service user is the auth context for /api/bot/* requests that
 * carry the Sanctum personal access token issued by trenchwars:bot:issue-token.
 * The sentinel string discord_id never collides with a real OAuth-provisioned
 * user because Discord snowflakes are pure digits (T-05-07-02 mitigation).
 */
class BotServiceUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['discord_id' => 'SYSTEM_BOT'],
            [
                'username' => 'Trenchwars Bot',
                'email' => 'bot@trenchwars.local',
                'locale' => 'en',
            ],
        );
    }
}
