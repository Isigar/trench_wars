<?php

declare(strict_types=1);

/*
| Source: 10-03-PLAN.md Task 1 — BotApiClanApplicationController happy path + 3 guard 422s.
|
| Covers CLAN-01 / CLAN-02 / CLAN-03 requirements for the bot endpoint:
|   POST /api/bot/clans/{clan:slug}/applications
|
| Mirroring BotApiMatchSignupTest fixture/helper pattern.
|
| Trust boundaries (from 10-03 threat model):
|   T-10-03-01 — route under abilities:bot:act-as-user + bot.acts-as; Auth::user() is
|                the rebound human (proven by X-Bot-Acts-As-User header lookup).
|   T-10-03-02 — neither controller nor bot supplies applicant_user_id; it comes
|                exclusively from the rebound auth user.
|   T-10-03-03 — all guards live in ClanApplicationService::apply(); controller
|                only delegates.
*/

use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\ClanMembership;
use App\Models\User;

/**
 * Build a bot-token bearer + acts-as headers for a given human discord_id.
 * Mirrored from BotApiMatchSignupTest::botAuthHeaders().
 *
 * @return array{0: User, 1: array<string, string>}
 */
function botClanAppAuthHeaders(string $humanDiscordId): array
{
    $bot = User::factory()->create(['discord_id' => '800000000000000001']);
    $token = $bot->createToken(
        name: 'bot-clan-app-test',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(30),
    );

    return [$bot, [
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => $humanDiscordId,
        'Accept' => 'application/json',
    ]];
}

it('returns 201 + application DTO when apply succeeds', function (): void {
    $clan = Clan::factory()->create([
        'accepts_applications' => true,
        'slug' => 'alpha-squad',
    ]);
    $human = User::factory()->create(['discord_id' => '100000000000000100']);
    [, $headers] = botClanAppAuthHeaders($human->discord_id);

    $this->withHeaders($headers)
        ->postJson("/api/bot/clans/{$clan->slug}/applications")
        ->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'clan_id', 'applicant_user_id', 'status'],
        ])
        ->assertJsonPath('data.status', 'pending');

    expect(
        ClanApplication::where('clan_id', $clan->id)
            ->where('applicant_user_id', $human->id)
            ->where('status', 'pending')
            ->exists()
    )->toBeTrue();
});

it('returns 422 clan_not_recruiting when clan accepts_applications=false', function (): void {
    $clan = Clan::factory()->create([
        'accepts_applications' => false,
        'slug' => 'closed-clan',
    ]);
    $human = User::factory()->create(['discord_id' => '100000000000000101']);
    [, $headers] = botClanAppAuthHeaders($human->discord_id);

    $this->withHeaders($headers)
        ->postJson("/api/bot/clans/{$clan->slug}/applications")
        ->assertStatus(422)
        ->assertJsonPath('error', 'clan_not_recruiting');

    expect(ClanApplication::where('clan_id', $clan->id)->count())->toBe(0);
});

it('returns 422 already_in_clan when human has an active membership', function (): void {
    $clan = Clan::factory()->create([
        'accepts_applications' => true,
        'slug' => 'member-already-clan',
    ]);
    $human = User::factory()->create(['discord_id' => '100000000000000102']);

    // Human is already a member of another clan.
    $otherClan = Clan::factory()->create();
    ClanMembership::factory()->create([
        'user_id' => $human->id,
        'clan_id' => $otherClan->id,
        'left_at' => null,
    ]);

    [, $headers] = botClanAppAuthHeaders($human->discord_id);

    $this->withHeaders($headers)
        ->postJson("/api/bot/clans/{$clan->slug}/applications")
        ->assertStatus(422)
        ->assertJsonPath('error', 'already_in_clan');

    expect(ClanApplication::where('clan_id', $clan->id)->count())->toBe(0);
});

it('returns 422 duplicate_application when pending application already exists', function (): void {
    $clan = Clan::factory()->create([
        'accepts_applications' => true,
        'slug' => 'duplicate-app-clan',
    ]);
    $human = User::factory()->create(['discord_id' => '100000000000000103']);

    // Pre-existing pending application.
    ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $human->id,
        'status' => 'pending',
    ]);

    [, $headers] = botClanAppAuthHeaders($human->discord_id);

    $this->withHeaders($headers)
        ->postJson("/api/bot/clans/{$clan->slug}/applications")
        ->assertStatus(422)
        ->assertJsonPath('error', 'duplicate_application');

    // Still only one pending application.
    expect(
        ClanApplication::where('clan_id', $clan->id)
            ->where('applicant_user_id', $human->id)
            ->where('status', 'pending')
            ->count()
    )->toBe(1);
});

// BL-02 — applying to an inactive clan via the bot API is also rejected.

it('returns 422 clan_not_recruiting when clan is suspended even if accepts_applications=true (BL-02)', function (): void {
    $clan = Clan::factory()->create([
        'status' => 'suspended',
        'accepts_applications' => true,
        'slug' => 'suspended-clan-bot',
    ]);
    $human = User::factory()->create(['discord_id' => '100000000000000104']);
    [, $headers] = botClanAppAuthHeaders($human->discord_id);

    $this->withHeaders($headers)
        ->postJson("/api/bot/clans/{$clan->slug}/applications")
        ->assertStatus(422)
        ->assertJsonPath('error', 'clan_not_recruiting');

    expect(ClanApplication::where('clan_id', $clan->id)->count())->toBe(0);
});
