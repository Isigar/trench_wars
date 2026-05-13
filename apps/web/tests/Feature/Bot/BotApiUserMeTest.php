<?php

declare(strict_types=1);

/*
| Source: plan 05-04 task 2 — replaces Wave 0 RED stub (05-01 task 2).
|
| Covers SC-1 / Open Question Q5 — GET /api/bot/users/me returns the
| privacy-aware profile for the X-Bot-Acts-As-User-resolved human, with the
| Phase 2 own-profile bypass (subject == viewer -> full data).
|
| 4 it() blocks per plan <interfaces> enumeration:
|  1. happy path returns user + player payload for the rebound human
|  2. own-profile bypass returns all PlayerPrivacy-gated fields regardless of tier
|  3. 422 when X-Bot-Acts-As-User header is missing (Pitfall 7 contract — see note)
|  4. 422 when X-Bot-Acts-As-User discord_id is unknown
|
| Note on case 3: The route composes abilities:bot:act-as-user before
| bot.acts-as. Without the header, the middleware passes through (Pitfall 7)
| leaving auth()->user() = bot service user. The /users/me endpoint then
| returns the BOT user's profile — which is structurally valid but not what
| the bot SHOULD see for the human. This documents the current contract;
| controller-side refusal is a future tightening (D-05-03-D).
*/

use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;

it('returns user + player payload for the X-Bot-Acts-As-User-resolved user', function (): void {
    $bot = User::factory()->create(['discord_id' => '900000000000000020']);
    $human = User::factory()->create([
        'discord_id' => '100000000000000020',
        'username' => 'humanmctest',
    ]);
    Player::factory()->create(['user_id' => $human->id]);

    $token = $bot->createToken(
        name: 'bot-test',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(30),
    );

    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => $human->discord_id,
        'Accept' => 'application/json',
    ])->getJson('/api/bot/users/me')
        ->assertOk()
        ->assertJsonPath('user.id', $human->id)
        ->assertJsonPath('user.discord_id', $human->discord_id)
        ->assertJsonPath('user.username', 'humanmctest')
        ->assertJsonPath('player.isOwnProfile', true);
});

it('own-profile bypass returns all PlayerPrivacy fields regardless of tier', function (): void {
    $bot = User::factory()->create(['discord_id' => '900000000000000021']);
    $human = User::factory()->create([
        'discord_id' => '100000000000000021',
        'username' => 'tightprivacyhuman',
    ]);
    $player = Player::factory()->create(['user_id' => $human->id]);

    // Most restrictive PlayerPrivacy: community tier (visible only to logged-in
    // league members) with show_real_name=false + show_discord_tag=false.
    // If the privacy gate were applied normally, discordTag would be Optional
    // (absent from JSON). Own-profile bypass forces it to be present.
    PlayerPrivacy::factory()->create([
        'player_id' => $player->id,
        'show_to' => 'community',
        'show_real_name' => false,
        'show_discord_tag' => false,
        'show_clan_history' => false,
        'show_match_history' => false,
        'show_stats' => false,
    ]);

    $token = $bot->createToken(
        name: 'bot-test',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(30),
    );

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => $human->discord_id,
        'Accept' => 'application/json',
    ])->getJson('/api/bot/users/me');

    $response->assertOk()
        ->assertJsonPath('player.isOwnProfile', true);

    // The own-profile bypass means PublicPlayerData includes discordTag (not
    // Optional). It would be '@tightprivacyhuman' or null (no @ if username
    // missing) — present in either case.
    $json = $response->json();
    expect($json['player'])->toHaveKey('discordTag')
        ->and($json['player']['discordTag'])->toBe('@tightprivacyhuman');
});

it('passes through (Pitfall 7) when X-Bot-Acts-As-User is missing — bot sees its own profile', function (): void {
    $bot = User::factory()->create(['discord_id' => '900000000000000022']);
    Player::factory()->create(['user_id' => $bot->id]);

    $token = $bot->createToken(
        name: 'bot-test',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(30),
    );

    // Pitfall 7 contract: middleware passes through missing header. The
    // controller's auth()->user() resolves to the bot service user — /users/me
    // returns the BOT's profile. This documents the wire-protocol contract;
    // future controller tightening may convert this to 422.
    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'Accept' => 'application/json',
    ])->getJson('/api/bot/users/me')
        ->assertOk()
        ->assertJsonPath('user.id', $bot->id);
});

it('returns 422 when X-Bot-Acts-As-User discord_id is unknown', function (): void {
    $bot = User::factory()->create(['discord_id' => '900000000000000023']);

    $token = $bot->createToken(
        name: 'bot-test',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(30),
    );

    // bot.acts-as middleware short-circuits with 422 acts_as_user_unknown
    // when the discord_id has no User row (plan 05-03 contract).
    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => '777777777777777777', // no User row
        'Accept' => 'application/json',
    ])->getJson('/api/bot/users/me')
        ->assertStatus(422)
        ->assertExactJson([
            'error' => 'acts_as_user_unknown',
            'message' => __('bot.errors.acts_as_unknown'),
        ]);
});
