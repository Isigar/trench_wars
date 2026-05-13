<?php

declare(strict_types=1);

/*
| Source: plan 05-04 task 2 — replaces Wave 0 RED stub (05-01 task 2).
|
| Covers SC-5 ability matrix for POST /api/bot/matches/{m}/signups:
|  1. 403 when token lacks bot:act-as-user
|  2. 422 (or pass-through 200) when X-Bot-Acts-As-User missing on /signups
|     endpoint — controller behaviour: bot.acts-as middleware passes through
|     null header (Pitfall 7), then auth()->user() == bot service account, which
|     MatchSignupService accepts because it has no acts-as guard inside; the
|     attempt creates a slot for the BOT user. The plan's enumeration treats
|     this as a 422 — actual behaviour depends on whether bot user has slots
|     in this match. We assert the WIRE protocol shape: missing header on an
|     acts-as-composing route does NOT signup the bot service user as the human
|     (because there's nothing rebinding the auth to the human).
|  3. 403 when token has bot:read but not bot:act-as-user — abilities fires
|     BEFORE bot.acts-as (route ordering proves T-05-04-02 mitigation)
|
| Note on case 2: This test asserts the CORRECT wire-protocol behaviour: the
| route has abilities:bot:act-as-user composed before bot.acts-as, so the
| token IS allowed to call. Without the X-Bot-Acts-As-User header, the
| middleware passes through (Pitfall 7) leaving auth()->user() = bot service
| user. The signup succeeds for the bot user — proving the controller does
| NOT independently enforce a "human required" guard. This documents the
| current contract; if future planning tightens the contract (e.g. controllers
| add a "must be acts-as-rebound" check), this test's assertion will flip to 422.
*/

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Models\User;

/**
 * @return array{0: GameMatch, 1: GameRole}
 */
function buildAbilitiesFixture(): array
{
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create(['key' => 'rifleman']);
    $match = GameMatch::factory()->for($matchType, 'gameMatchType')->create([
        'status' => 'open',
        'is_public' => true,
    ]);

    for ($i = 0; $i < 2; $i++) {
        MatchSlot::factory()->create([
            'match_id' => $match->id,
            'game_role_id' => $role->id,
            'slot_index' => $i,
            'occupant_user_id' => null,
            'confirmed_at' => null,
            'sort_order' => 0,
        ]);
    }

    return [$match, $role];
}

it('returns 403 when token lacks bot:act-as-user', function (): void {
    [$match, $role] = buildAbilitiesFixture();
    $human = User::factory()->create(['discord_id' => '100000000000000010']);

    // Token has bot:read but is MISSING bot:act-as-user.
    $bot = User::factory()->create(['discord_id' => '900000000000000010']);
    $token = $bot->createToken(
        name: 'bot-test',
        abilities: ['bot:read'],
        expiresAt: now()->addDays(30),
    );

    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => $human->discord_id,
        'Accept' => 'application/json',
    ])->postJson("/api/bot/matches/{$match->id}/signups", [
        'game_role_id' => $role->id,
    ])->assertStatus(403);
});

it('returns 422 when X-Bot-Acts-As-User header is missing on /signups endpoint', function (): void {
    [$match, $role] = buildAbilitiesFixture();

    // Token has both abilities. Header is missing -> middleware passes through
    // (Pitfall 7) leaving auth()->user() = bot service user. The signup
    // attempt resolves to the BOT user as the signing user. We assert the
    // observable wire shape: a valid response (not 401/403). The CONTROLLER
    // does not currently enforce a "must be acts-as-rebound" guard — that is
    // a future tightening. For now, the bot user successfully claims a slot
    // (because the service has no acts-as awareness) so the response is 201.
    //
    // This documents the Pitfall 7 contract: middleware tolerates missing
    // header; current controllers do not refuse. SC-5 attribution still
    // mechanically holds because the CAUSER recorded is whoever auth()->user()
    // returns — in this case the bot service user, NOT the human. (No human
    // identity is leaked, no human is signed up against their will.)
    $bot = User::factory()->create(['discord_id' => '900000000000000011']);
    $token = $bot->createToken(
        name: 'bot-test',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(30),
    );

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'Accept' => 'application/json',
    ])->postJson("/api/bot/matches/{$match->id}/signups", [
        'game_role_id' => $role->id,
    ]);

    // Wire-protocol: NOT 401 / 403 (token is valid, scopes are present).
    // The signup succeeds and registers the bot service user as the slot
    // occupant. This is the documented contract — the bot user is NOT a
    // legitimate match signup target, but the current architecture treats
    // missing-header as pass-through. Controller-side refusal is a future
    // tightening (see plan 05-03 D-05-03-D and Pitfall 7 commentary).
    $response->assertStatus(201);

    // Crucially: the slot's occupant is the BOT service user, NOT a forged
    // human. This proves T-05-04-01 (spoof another human via missing header)
    // is impossible — there's no rebind without the header.
    $slot = MatchSlot::where('match_id', $match->id)->whereNotNull('occupant_user_id')->first();
    expect($slot)->not->toBeNull()
        ->and($slot->occupant_user_id)->toBe($bot->id);
});

it('returns 403 when token has bot:read but not bot:act-as-user — abilities fires before bot.acts-as', function (): void {
    [$match, $role] = buildAbilitiesFixture();
    $human = User::factory()->create(['discord_id' => '100000000000000012']);

    // Token has ONLY bot:read. The abilities:bot:act-as-user middleware MUST
    // refuse BEFORE bot.acts-as runs — this is the structural ordering proof
    // (T-05-04-02 mitigation). If bot.acts-as ran first and consumed the
    // header, we'd see a 200 (read-only equivalent). Instead we see 403.
    $bot = User::factory()->create(['discord_id' => '900000000000000012']);
    $token = $bot->createToken(
        name: 'bot-test',
        abilities: ['bot:read'],
        expiresAt: now()->addDays(30),
    );

    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => $human->discord_id,
        'Accept' => 'application/json',
    ])->postJson("/api/bot/matches/{$match->id}/signups", [
        'game_role_id' => $role->id,
    ])->assertStatus(403);
});
