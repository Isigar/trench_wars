<?php

declare(strict_types=1);

/*
| Source: 05-12-PLAN.md task 3 — the SC-5 CAPSTONE for Phase 5 Discord bot v1.
|
| This is the end-to-end integration test that proves the entire bot-side auth
| chain works: bot Sanctum token + X-Bot-Acts-As-User header + signup endpoint
| + MatchSignupService write + LogsActivity trip yields an activity_log row
| whose causer_id is the HUMAN Discord user, NOT the bot service account.
|
| Trust chain proven (each link breaks an SC-5 contract if it drifts):
|   1. Sanctum bearer authenticates the bot service-account User
|   2. The abilities middleware grants ['bot:read', 'bot:act-as-user']
|   3. The bot.acts-as middleware (plan 05-03) resolves the discord_id header
|      to a human User and rebinds Auth::user() via Auth::setUser()
|   4. The signup controller resolves auth()->user() → the rebound human
|   5. MatchSignupService::signup() writes MatchSlot.occupant_user_id = human
|   6. LogsActivity captures the active auth user as activity_log.causer_id
|
| Threat refs:
|   T-05-12-01 (Repudiation — bot causer drifts to the service account, defeating
|              SC-5) — this test fails LOUDLY if the rebind ever breaks.
|
| Analog (less focused): tests/Feature/Bot/BotApiMatchSignupTest.php has a
|   `records activity_log row with causer = ...` it() block already. THIS file
|   isolates the SC-5 capstone with the defence-in-depth NOT-bot assertion as
|   its primary contract, plus negative-path coverage so a future plan that
|   tightens the rebind contract can lean on this file.
*/

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Build a same-game (match, role) fixture with $slotCapacity empty slots.
 *
 * @return array{0: GameMatch, 1: GameRole}
 */
function buildCapstoneSignupFixture(int $slotCapacity = 2, string $status = 'open'): array
{
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create(['key' => 'rifleman']);
    $match = GameMatch::factory()->for($matchType, 'gameMatchType')->create([
        'status' => $status,
        'is_public' => true,
    ]);

    for ($i = 0; $i < $slotCapacity; $i++) {
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

/**
 * Build a bot service user + Sanctum token with the abilities required for
 * the /api/bot/matches/{m}/signups endpoint.
 *
 * @return array{0: User, 1: string}
 */
function provisionBotAndToken(): array
{
    $bot = User::factory()->create([
        'discord_id' => 'SYSTEM_BOT_CAPSTONE',
        'username' => 'Trenchwars Bot (capstone)',
    ]);
    $token = $bot->createToken(
        name: 'capstone-test',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDay(),
    );

    return [$bot, $token->plainTextToken];
}

it('SC-5 capstone: signup via bot token + X-Bot-Acts-As-User attributes causer_id to the human, NOT the bot service account', function (): void {
    [$match, $role] = buildCapstoneSignupFixture();

    // 1. Bot service user + token (the Sanctum bearer authentication target).
    [$bot, $plainTextToken] = provisionBotAndToken();

    // 2. Real human Discord user who logged in via OAuth previously.
    $human = User::factory()->create(['discord_id' => '100000000000000001']);

    // 3. Fire signup with the bot's bearer + the human's discord_id in the
    //    X-Bot-Acts-As-User header. The bot.acts-as middleware (plan 05-03)
    //    rebinds Auth::user() to $human for the request lifetime.
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $plainTextToken,
        'X-Bot-Acts-As-User' => '100000000000000001',
        'Accept' => 'application/json',
    ])->postJson("/api/bot/matches/{$match->id}/signups", [
        'game_role_id' => $role->id,
    ]);

    $response->assertCreated();

    // 4. MatchSlot.occupant_user_id is the HUMAN. If the rebind didn't fire,
    //    the slot would belong to the bot service user instead.
    $slot = MatchSlot::query()
        ->where('match_id', $match->id)
        ->where('occupant_user_id', $human->id)
        ->first();

    expect($slot)->not->toBeNull(
        'MatchSlot.occupant_user_id is not the rebound human — the bot.acts-as middleware may have failed to rebind Auth::user().'
    );
    expect($slot->occupant_user_id)->toBe($human->id)
        ->and($slot->occupant_user_id)->not->toBe($bot->id);

    // 5. activity_log row for the MatchSlot.occupant_user_id update has the
    //    HUMAN as causer. THE SC-5 GUARANTEE.
    $activity = Activity::query()
        ->where('subject_type', MatchSlot::class)
        ->where('subject_id', $slot->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($human->id)               // <-- THE SC-5 contract
        ->and($activity->causer_id)->not->toBe($bot->id)            // <-- defence-in-depth
        ->and($activity->causer_type)->toBe(User::class);
});

it('returns 422 when X-Bot-Acts-As-User points at a non-existent discord_id (no rebind occurs)', function (): void {
    [$match, $role] = buildCapstoneSignupFixture();
    [, $plainTextToken] = provisionBotAndToken();

    // No User row with discord_id='100000000000000099' exists — the middleware
    // returns 422 acts_as_user_unknown without writing anything.
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $plainTextToken,
        'X-Bot-Acts-As-User' => '100000000000000099',
        'Accept' => 'application/json',
    ])->postJson("/api/bot/matches/{$match->id}/signups", [
        'game_role_id' => $role->id,
    ]);

    $response->assertStatus(422)
        ->assertExactJson([
            'error' => 'acts_as_user_unknown',
            'message' => __('bot.errors.acts_as_unknown'),
        ]);

    // No MatchSlot was claimed — every slot is still unoccupied.
    $claimedSlots = MatchSlot::query()
        ->where('match_id', $match->id)
        ->whereNotNull('occupant_user_id')
        ->count();

    expect($claimedSlots)->toBe(0);
});

it('without X-Bot-Acts-As-User header the bot service account stays as auth context (defence — read-only endpoint reachable but signup endpoint would NOT attribute to a human)', function (): void {
    [$match, $role] = buildCapstoneSignupFixture();
    [$bot, $plainTextToken] = provisionBotAndToken();

    // Fire signup without acts-as. The middleware tolerates a missing header
    // (Pitfall 7) and lets the request continue under the bot's identity. The
    // signup will succeed — but with the BOT as occupant_user_id, which is
    // exactly the failure mode SC-5 protects against. We assert this attribution
    // explicitly so the contrast with the SC-5 capstone (above) is mechanically
    // observable: removing the acts-as header drops the causer back to the bot.
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $plainTextToken,
        'Accept' => 'application/json',
    ])->postJson("/api/bot/matches/{$match->id}/signups", [
        'game_role_id' => $role->id,
    ]);

    $response->assertCreated();

    $slot = MatchSlot::query()
        ->where('match_id', $match->id)
        ->whereNotNull('occupant_user_id')
        ->first();

    expect($slot)->not->toBeNull()
        ->and($slot->occupant_user_id)->toBe($bot->id);

    // This is the CONTRA case: in production no bot endpoint that performs
    // human writes is reachable without the X-Bot-Acts-As-User header (plan
    // 05-04 wires the abilities:bot:act-as-user middleware BEFORE the route).
    // For this test we documented the bot.acts-as middleware's tolerance
    // contract (Pitfall 7) so future operators reading this suite see the
    // explicit attribution drop when the header is absent.
    $activity = Activity::query()
        ->where('subject_type', MatchSlot::class)
        ->where('subject_id', $slot->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($bot->id);
});
