<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Source: .planning/phases/05-discord-bot-v1/05-07-PLAN.md task 2
 * + 05-RESEARCH.md Pitfall 3 (token rotation) + Open Question Q4 (bot service user).
 *
 * Issues a Sanctum personal access token for the bot service user (sentinel
 * `discord_id='SYSTEM_BOT'`). The token is shown ONCE — Sanctum stores only
 * the SHA-256 hash in personal_access_tokens.token, so the plaintext cannot
 * be recovered after this command exits. The operator copies it into Railway's
 * WEB_API_TOKEN env var (or apps/web/.env for local dev).
 *
 * Rotation safety (T-05-07-04): any existing token with the same --name is
 * deleted before the new one is issued, so running this command twice never
 * leaves two valid tokens floating around.
 *
 * The bot service user is created idempotently on first run — subsequent
 * invocations reuse the same User row (firstOrCreate keyed by discord_id).
 *
 * T-05-07-02 mitigation: the sentinel `discord_id='SYSTEM_BOT'` (non-numeric
 * string) cannot collide with a real OAuth-provisioned user — Discord snowflakes
 * are pure digits.
 */
class IssueBotTokenCommand extends Command
{
    protected $signature = 'trenchwars:bot:issue-token {--name=bot-prod} {--ttl=90}';

    protected $description = 'Issue a Sanctum personal access token for the bot service user (rotation: every 90 days).';

    public function handle(): int
    {
        $bot = User::firstOrCreate(
            ['discord_id' => 'SYSTEM_BOT'],
            [
                'username' => 'Trenchwars Bot',
                'email' => 'bot@trenchwars.local',
                'locale' => 'en',
            ],
        );

        $name = (string) $this->option('name');
        $ttl = (int) $this->option('ttl');

        // Rotation safety (T-05-07-04): delete any prior token with the same name.
        $bot->tokens()->where('name', $name)->delete();

        $token = $bot->createToken(
            $name,
            ['bot:read', 'bot:act-as-user', 'bot:write-outbound', 'bot:reconcile'],
            now()->addDays($ttl),
        );

        $expiresAt = $token->accessToken->expires_at;
        $expiresAtFormatted = $expiresAt !== null ? $expiresAt->toIso8601String() : 'never';

        $this->info('Bot service user: ' . $bot->id);
        $this->info('Token name: ' . $name);
        $this->info('Expires at: ' . $expiresAtFormatted);
        $this->newLine();
        $this->warn('Copy the token below — it is shown ONCE and cannot be recovered.');
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->comment('Paste into Railway bot service WEB_API_TOKEN env var (or apps/web/.env for local dev).');
        $this->comment('Run trenchwars:bot:revoke-token --name=' . $name . ' to revoke.');

        return self::SUCCESS;
    }
}
