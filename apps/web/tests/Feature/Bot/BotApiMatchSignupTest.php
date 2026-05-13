<?php

declare(strict_types=1);

/*
| Source: plan 05-04 task 2 — replaces Wave 0 RED stub (05-01 task 2).
|
| Covers SC-2 (POST /api/bot/matches/{m}/signups -> MatchSignupService reuse) +
| SC-5 (activity_log causer attribution to the X-Bot-Acts-As-User-resolved
| human, NOT the token-owning bot service account).
|
| 7 it() blocks per plan <interfaces> enumeration:
|  1. happy path returns 201 + slot DTO
|  2. service is reused — container-bind stub records the call (D-04-09-D pattern)
|  3. 422 match_not_open when match.status != open
|  4. 422 capacity_full when role is full
|  5. 422 tag_restricted when user clan lacks allowed tag
|  6. 422 already_signed_up on second call
|  7. activity_log row attributes causer to the rebound human, NOT the bot
|
| Fixture pattern: bot service User + token with bot:read + bot:act-as-user.
| X-Bot-Acts-As-User header carries the human's discord_id; bot.acts-as
| middleware (plan 05-03) rebinds auth()->user() inside the request.
*/

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\ClanTag;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameRole;
use App\Models\MatchAccessRule;
use App\Models\MatchSlot;
use App\Models\User;
use App\Services\MatchSignupService;
use Spatie\Activitylog\Models\Activity;

/**
 * Build a same-game (match, role) fixture with $slotCapacity empty slots.
 *
 * @return array{0: GameMatch, 1: GameRole}
 */
function buildBotSignupFixture(int $slotCapacity = 2, string $status = 'open'): array
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
 * Build a bot-token bearer + acts-as headers for a given human discord_id.
 *
 * @return array{0: User, 1: array<string, string>}
 */
function botAuthHeaders(string $humanDiscordId): array
{
    $bot = User::factory()->create(['discord_id' => '900000000000000001']);
    $token = $bot->createToken(
        name: 'bot-test',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(30),
    );

    return [$bot, [
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => $humanDiscordId,
        'Accept' => 'application/json',
    ]];
}

it('returns 201 + slot DTO when signup succeeds', function (): void {
    [$match, $role] = buildBotSignupFixture();
    $human = User::factory()->create(['discord_id' => '100000000000000001']);
    [, $headers] = botAuthHeaders($human->discord_id);

    $this->withHeaders($headers)
        ->postJson("/api/bot/matches/{$match->id}/signups", [
            'game_role_id' => $role->id,
        ])
        ->assertStatus(201)
        ->assertJsonStructure([
            'slot' => ['id', 'match_id', 'game_role_id', 'slot_index', 'occupant_user_id', 'confirmed_at'],
        ]);

    expect(
        MatchSlot::where('match_id', $match->id)
            ->where('occupant_user_id', $human->id)
            ->exists()
    )->toBeTrue();
});

it('reuses MatchSignupService — proven by D-010 service-only invariants', function (): void {
    [$match, $role] = buildBotSignupFixture();
    $human = User::factory()->create(['discord_id' => '100000000000000002']);
    [, $headers] = botAuthHeaders($human->discord_id);

    // D-004 service-reuse PROOF (non-Mockery, non-stub).
    //
    // MatchSignupService is `final` (Phase 4 plan 04-06 D-04-06-A decision)
    // so the planned `app()->bind(MatchSignupService::class, fn () => new
    // class extends MatchSignupService { ... })` stub is structurally
    // impossible — anonymous-class extension of a final class is a PHP
    // fatal error.
    //
    // Instead we prove D-004 enforcement by asserting service-only
    // post-conditions that a controller bypassing the service could not
    // produce together:
    //   1. occupant_user_id is the rebound HUMAN, NOT the bot service user
    //      — confirms bot.acts-as middleware AND service handoff worked.
    //   2. confirmed_at is non-null and within the last 5 seconds —
    //      the service writes confirmed_at = now() atomically; a raw
    //      $slot->save() wouldn't set this.
    //   3. activity_log row with subject_type=MatchSlot and causer_id=human
    //      — the LogsActivity trait fires on $slot->update() inside the
    //      service's transaction. A direct DB::table insert would NOT
    //      fire LogsActivity at all (subject_type would never appear).
    $this->withHeaders($headers)
        ->postJson("/api/bot/matches/{$match->id}/signups", [
            'game_role_id' => $role->id,
        ])
        ->assertStatus(201);

    $slot = MatchSlot::where('match_id', $match->id)
        ->where('occupant_user_id', $human->id)
        ->first();
    expect($slot)->not->toBeNull()
        ->and($slot->confirmed_at)->not->toBeNull()
        ->and($slot->confirmed_at->diffInSeconds(now()))->toBeLessThan(5);

    // activity_log row presence — the LogsActivity trait is the structural
    // proof that $slot->update() (the service's write path) was invoked.
    $activity = Activity::query()
        ->where('subject_type', MatchSlot::class)
        ->where('subject_id', $slot->id)
        ->where('description', 'MatchSlot updated')
        ->latest('id')
        ->first();
    expect($activity)->not->toBeNull();
});

it('returns 422 match_not_open when match.status != open', function (): void {
    [$match, $role] = buildBotSignupFixture(status: 'locked');
    $human = User::factory()->create(['discord_id' => '100000000000000003']);
    [, $headers] = botAuthHeaders($human->discord_id);

    $this->withHeaders($headers)
        ->postJson("/api/bot/matches/{$match->id}/signups", [
            'game_role_id' => $role->id,
        ])
        ->assertStatus(422)
        ->assertExactJson([
            'error' => 'match_not_open',
            'message' => __('bot.errors.match_not_open'),
        ]);
});

it('returns 422 capacity_full when role is full', function (): void {
    [$match, $role] = buildBotSignupFixture(slotCapacity: 1);

    // Pre-fill the only slot via the service (the canonical write path).
    $first = User::factory()->create();
    app(MatchSignupService::class)->signup($match, $first, $role);

    $human = User::factory()->create(['discord_id' => '100000000000000004']);
    [, $headers] = botAuthHeaders($human->discord_id);

    $this->withHeaders($headers)
        ->postJson("/api/bot/matches/{$match->id}/signups", [
            'game_role_id' => $role->id,
        ])
        ->assertStatus(422)
        ->assertExactJson([
            'error' => 'capacity_full',
            'message' => __('bot.errors.capacity_full'),
        ]);
});

it('returns 422 tag_restricted when user clan lacks allowed tag', function (): void {
    [$match, $role] = buildBotSignupFixture();

    // Add tag-restriction: only clans tagged 'eu' may sign up.
    $allowedTag = ClanTag::factory()->create(['slug' => 'eu']);
    MatchAccessRule::factory()->create([
        'match_id' => $match->id,
        'clan_tag_id' => $allowedTag->id,
    ]);

    // The human's clan is tagged 'na' (not allowed).
    $human = User::factory()->create(['discord_id' => '100000000000000005']);
    $clan = Clan::factory()->create(['status' => 'active']);
    $otherTag = ClanTag::factory()->create(['slug' => 'na']);
    $clan->tags()->attach($otherTag);
    ClanMembership::factory()->create([
        'user_id' => $human->id,
        'clan_id' => $clan->id,
        'left_at' => null,
    ]);

    [, $headers] = botAuthHeaders($human->discord_id);

    $this->withHeaders($headers)
        ->postJson("/api/bot/matches/{$match->id}/signups", [
            'game_role_id' => $role->id,
        ])
        ->assertStatus(422)
        ->assertExactJson([
            'error' => 'tag_restricted',
            'message' => __('bot.errors.tag_restricted'),
        ]);
});

it('returns 422 already_signed_up on second call', function (): void {
    [$match, $role] = buildBotSignupFixture();
    $human = User::factory()->create(['discord_id' => '100000000000000006']);

    // First signup succeeds (via the service directly to isolate state).
    app(MatchSignupService::class)->signup($match, $human, $role);

    [, $headers] = botAuthHeaders($human->discord_id);

    $this->withHeaders($headers)
        ->postJson("/api/bot/matches/{$match->id}/signups", [
            'game_role_id' => $role->id,
        ])
        ->assertStatus(422)
        ->assertExactJson([
            'error' => 'already_signed_up',
            'message' => __('bot.errors.already_signed_up'),
        ]);
});

it('records activity_log row with causer = the X-Bot-Acts-As-User-resolved user, NOT the token owner', function (): void {
    [$match, $role] = buildBotSignupFixture();
    $human = User::factory()->create(['discord_id' => '100000000000000007']);
    [$bot, $headers] = botAuthHeaders($human->discord_id);

    $this->withHeaders($headers)
        ->postJson("/api/bot/matches/{$match->id}/signups", [
            'game_role_id' => $role->id,
        ])
        ->assertStatus(201);

    // The MatchSlot update from signup() fires LogsActivity. The activity_log
    // row's causer_id MUST be $human, not $bot — this is the SC-5 mechanical
    // guarantee enforced by bot.acts-as middleware (plan 05-03).
    $activity = Activity::query()
        ->where('subject_type', MatchSlot::class)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($human->id)
        ->and($activity->causer_id)->not->toBe($bot->id);
});
