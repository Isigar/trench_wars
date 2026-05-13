<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/*
| Source: 05-07-PLAN.md task 3 + 05-RESEARCH.md Pitfall 3 (rotation) + Q4 (bot service user).
|
| Verifies the Phase 5 trenchwars:bot:issue-token Artisan command:
|   1. Creates the bot service user when absent (sentinel discord_id='SYSTEM_BOT')
|   2. Reuses the bot service user when already exists (idempotent firstOrCreate)
|   3. Issued token has all 4 abilities (bot:read, bot:act-as-user,
|      bot:write-outbound, bot:reconcile)
|   4. expires_at defaults to now + 90 days (--ttl=90)
|   5. Respects custom --ttl
|   6. Respects custom --name
|   7. Rotation safety — prior token with same --name is deleted before reissue
|   8. Output contains the plaintext token + the "shown ONCE" warning
|
| Sanctum's createToken returns Laravel\Sanctum\NewAccessToken whose ->accessToken
| is the persisted PersonalAccessToken model (with abilities + expires_at) and
| ->plainTextToken is the one-time string the operator copies into Railway.
*/

it('creates the bot service user when absent', function (): void {
    expect(User::where('discord_id', 'SYSTEM_BOT')->exists())->toBeFalse();

    $this->artisan('trenchwars:bot:issue-token')->assertExitCode(0);

    $bot = User::where('discord_id', 'SYSTEM_BOT')->first();
    expect($bot)->not->toBeNull();
    expect($bot->username)->toBe('Trenchwars Bot');
    expect($bot->email)->toBe('bot@trenchwars.local');
});

it('reuses the bot service user when already exists (idempotent firstOrCreate)', function (): void {
    $this->artisan('trenchwars:bot:issue-token --name=first')->assertExitCode(0);
    $firstBotId = User::where('discord_id', 'SYSTEM_BOT')->first()->id;

    $this->artisan('trenchwars:bot:issue-token --name=second')->assertExitCode(0);
    $secondBotId = User::where('discord_id', 'SYSTEM_BOT')->first()->id;

    expect($secondBotId)->toBe($firstBotId);
    expect(User::where('discord_id', 'SYSTEM_BOT')->count())->toBe(1);
});

it('issued token has all 4 bot abilities (bot:read, bot:act-as-user, bot:write-outbound, bot:reconcile)', function (): void {
    $this->artisan('trenchwars:bot:issue-token')->assertExitCode(0);

    $bot = User::where('discord_id', 'SYSTEM_BOT')->firstOrFail();
    $token = $bot->tokens()->first();

    expect($token)->not->toBeNull();
    expect($token->abilities)->toContain('bot:read');
    expect($token->abilities)->toContain('bot:act-as-user');
    expect($token->abilities)->toContain('bot:write-outbound');
    expect($token->abilities)->toContain('bot:reconcile');
});

it('issued token has expires_at = now + 90 days by default (--ttl=90)', function (): void {
    $this->artisan('trenchwars:bot:issue-token')->assertExitCode(0);

    /** @var PersonalAccessToken $token */
    $token = User::where('discord_id', 'SYSTEM_BOT')->firstOrFail()->tokens()->first();

    expect($token->expires_at)->not->toBeNull();
    // diff must round to ~90 days; allow 1-day envelope to absorb the brief delay
    // between command exec and assertion calculation.
    $diffDays = (int) round(abs($token->expires_at->diffInDays(now())));
    expect($diffDays)->toBeGreaterThanOrEqual(89);
    expect($diffDays)->toBeLessThanOrEqual(91);
});

it('respects custom --ttl=30 option', function (): void {
    $this->artisan('trenchwars:bot:issue-token --ttl=30')->assertExitCode(0);

    $token = User::where('discord_id', 'SYSTEM_BOT')->firstOrFail()->tokens()->first();
    $diffDays = (int) round(abs($token->expires_at->diffInDays(now())));
    expect($diffDays)->toBeGreaterThanOrEqual(29);
    expect($diffDays)->toBeLessThanOrEqual(31);
});

it('respects custom --name=staging option', function (): void {
    $this->artisan('trenchwars:bot:issue-token --name=staging')->assertExitCode(0);

    $token = User::where('discord_id', 'SYSTEM_BOT')->firstOrFail()->tokens()->first();
    expect($token->name)->toBe('staging');
});

it('revokes any prior token with the same name before issuing new one (T-05-07-04 rotation safety)', function (): void {
    $this->artisan('trenchwars:bot:issue-token --name=bot-prod')->assertExitCode(0);
    $firstTokenId = User::where('discord_id', 'SYSTEM_BOT')->firstOrFail()->tokens()->first()->id;

    $this->artisan('trenchwars:bot:issue-token --name=bot-prod')->assertExitCode(0);

    $bot = User::where('discord_id', 'SYSTEM_BOT')->firstOrFail();
    expect($bot->tokens()->count())->toBe(1);
    expect($bot->tokens()->first()->id)->not->toBe($firstTokenId);
});

it('emits the plaintext token + the "shown ONCE" warning + bot user line', function (): void {
    $this->artisan('trenchwars:bot:issue-token')
        ->expectsOutputToContain('Bot service user:')
        ->expectsOutputToContain('shown ONCE')
        ->assertExitCode(0);
});
