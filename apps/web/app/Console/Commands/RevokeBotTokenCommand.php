<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Source: .planning/phases/05-discord-bot-v1/05-07-PLAN.md task 2.
 *
 * Revokes the named Sanctum personal access token for the bot service user.
 * Companion to IssueBotTokenCommand — together they implement the documented
 * rotation playbook (RESEARCH §Pitfall 3).
 *
 * Sad path: if the bot service user doesn't exist (issue-token has never run),
 * report and exit FAILURE — there is nothing to revoke.
 */
class RevokeBotTokenCommand extends Command
{
    protected $signature = 'trenchwars:bot:revoke-token {--name=bot-prod}';

    protected $description = 'Revoke the named Sanctum personal access token for the bot service user.';

    public function handle(): int
    {
        $bot = User::where('discord_id', 'SYSTEM_BOT')->first();
        if ($bot === null) {
            $this->error('Bot service user not found. Run trenchwars:bot:issue-token first.');

            return self::FAILURE;
        }

        $name = (string) $this->option('name');
        $deleted = $bot->tokens()->where('name', $name)->delete();

        $this->info("Deleted {$deleted} token(s) named '{$name}'.");

        return self::SUCCESS;
    }
}
