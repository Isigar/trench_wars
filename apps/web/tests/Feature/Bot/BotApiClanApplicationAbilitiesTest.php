<?php

declare(strict_types=1);

/*
| Source: 10-03-PLAN.md Task 1 — SC-ability matrix for POST /api/bot/clans/{slug}/applications.
|
| Mirrors BotApiMatchSignupAbilitiesTest structure verbatim, adapted for the
| clan-applications endpoint.
|
| T-10-03-01 mitigation proof:
|   1. 403 when token lacks bot:act-as-user (abilities middleware fires before bot.acts-as).
|   2. Token with bot:act-as-user but missing X-Bot-Acts-As-User header — passes through
|      with auth()->user() = bot service account (Pitfall 7 contract documented).
|   3. 403 when token has bot:read but not bot:act-as-user.
*/

use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\User;

/**
 * Build an open clan fixture for abilities tests.
 */
function buildClanAppAbilitiesFixture(): Clan
{
    return Clan::factory()->create([
        'accepts_applications' => true,
        'slug' => 'abilities-test-clan',
    ]);
}

it('returns 403 when token lacks bot:act-as-user', function (): void {
    $clan = buildClanAppAbilitiesFixture();
    $human = User::factory()->create(['discord_id' => '100000000000000200']);

    // Token has bot:read but is MISSING bot:act-as-user.
    $bot = User::factory()->create(['discord_id' => '900000000000000200']);
    $token = $bot->createToken(
        name: 'bot-abilities-test',
        abilities: ['bot:read'],
        expiresAt: now()->addDays(30),
    );

    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => $human->discord_id,
        'Accept' => 'application/json',
    ])->postJson("/api/bot/clans/{$clan->slug}/applications")
        ->assertStatus(403);
});

it('returns 201 when X-Bot-Acts-As-User header is missing — bot service user applies (Pitfall 7 contract)', function (): void {
    $clan = buildClanAppAbilitiesFixture();

    // Token has both abilities. Header is missing -> middleware passes through
    // (Pitfall 7) leaving auth()->user() = bot service user.
    // The application is created for the bot service user — no human is spoofed.
    $bot = User::factory()->create(['discord_id' => '900000000000000201']);
    $token = $bot->createToken(
        name: 'bot-abilities-test',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(30),
    );

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'Accept' => 'application/json',
    ])->postJson("/api/bot/clans/{$clan->slug}/applications");

    // Wire-protocol: NOT 401 / 403 (token is valid, scopes are present).
    // The application is created for the bot service user.
    $response->assertStatus(201);

    // Crucially: the application's applicant is the BOT user, NOT a forged human.
    // This proves T-10-03-01 (spoofing another human via missing header) is impossible.
    $app = ClanApplication::where('clan_id', $clan->id)
        ->where('applicant_user_id', $bot->id)
        ->first();

    expect($app)->not->toBeNull()
        ->and($app->status)->toBe('pending');
});

it('returns 403 when token has bot:read but not bot:act-as-user — abilities fires before bot.acts-as', function (): void {
    $clan = buildClanAppAbilitiesFixture();
    $human = User::factory()->create(['discord_id' => '100000000000000202']);

    // Token has ONLY bot:read. The abilities:bot:act-as-user middleware MUST
    // refuse BEFORE bot.acts-as runs — structural ordering proof (T-10-03-01).
    $bot = User::factory()->create(['discord_id' => '900000000000000202']);
    $token = $bot->createToken(
        name: 'bot-abilities-test',
        abilities: ['bot:read'],
        expiresAt: now()->addDays(30),
    );

    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => $human->discord_id,
        'Accept' => 'application/json',
    ])->postJson("/api/bot/clans/{$clan->slug}/applications")
        ->assertStatus(403);
});
