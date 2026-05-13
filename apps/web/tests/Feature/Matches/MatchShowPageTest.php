<?php

declare(strict_types=1);

/*
| Source: 04-10-PLAN.md Task 2 — replaces Wave 0 RED stub.
|
| Covers SC-3 second half: GET /matches/{match} renders Inertia 'Matches/Show'
| with PublicMatchData + role-grouped PublicMatchOccupantData slots; private
| matches return 404 for non-organisers (T-04-10-02); PlayerPrivacyGate
| withholds occupant displayName when show_match_history=false (T-04-10-01);
| clan_tag stays non-null even when displayName is withheld (D-008).
|
| Privacy assertion approach: Inertia testing helpers walk the prop tree.
| `where('roleGroups.0.slots.0.displayName', null)` asserts the privacy strip;
| `where('roleGroups.0.slots.0.clanTag', 'eu')` asserts the D-008 carve-out.
|
| NAMING NOTE (D-04-03-A): Match model is GameMatch.
*/

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\ClanTag;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Build a public open match with one role + one empty slot. Returns [$match, $role].
 *
 * @return array{0: GameMatch, 1: GameRole}
 */
function buildShowFixture(string $status = 'open', bool $isPublic = true): array
{
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create(['key' => 'rifleman', 'sort_order' => 1]);
    $match = GameMatch::factory()->for($matchType, 'gameMatchType')->create([
        'status' => $status,
        'is_public' => $isPublic,
        'scheduled_at' => now()->addDays(2),
    ]);

    MatchSlot::factory()->create([
        'match_id' => $match->id,
        'game_role_id' => $role->id,
        'slot_index' => 0,
        'occupant_user_id' => null,
        'confirmed_at' => null,
        'sort_order' => 0,
    ]);

    return [$match, $role];
}

// ───── Reachability + 404 guard ────────────────────────────────────────────

it('GET /matches/{match} returns 200 for public match without auth', function (): void {
    [$match] = buildShowFixture();

    $this->get(route('matches.show', $match))
        ->assertStatus(200);
});

it('GET /matches/{match} returns 404 for private match guest viewer', function (): void {
    [$match] = buildShowFixture(isPublic: false);

    $this->get(route('matches.show', $match))
        ->assertStatus(404);
});

it('GET /matches/{match} returns 200 for private match when viewer is organiser', function (): void {
    [$match] = buildShowFixture(isPublic: false);
    /** @var User $organiser */
    $organiser = User::find($match->organiser_user_id);

    $this->actingAs($organiser)
        ->get(route('matches.show', $match))
        ->assertStatus(200);
});

it('GET /matches/{match} returns 404 for non-existent UUID', function (): void {
    $this->get('/matches/00000000-0000-0000-0000-000000000000')
        ->assertStatus(404);
});

// ───── Inertia component + props shape ─────────────────────────────────────

it('GET /matches/{match} renders Matches/Show with PublicMatchData + roleGroups', function (): void {
    [$match] = buildShowFixture();

    $this->get(route('matches.show', $match))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Matches/Show', false) // Vue page lands in plan 04-11; skip existence check.
                ->has('match.id')
                ->where('match.id', $match->id)
                ->has('roleGroups')
                ->has('signupAllowed')
                ->has('viewerSlotId')
        );
});

it('roleGroups prop groups slots by game_role_id correctly', function (): void {
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $roleA = GameRole::factory()->for($game)->create(['key' => 'rifleman', 'sort_order' => 1]);
    $roleB = GameRole::factory()->for($game)->create(['key' => 'medic', 'sort_order' => 2]);
    $match = GameMatch::factory()->for($matchType, 'gameMatchType')->create([
        'status' => 'open',
        'is_public' => true,
    ]);

    // 3 slots in roleA, 2 slots in roleB.
    for ($i = 0; $i < 3; $i++) {
        MatchSlot::factory()->create([
            'match_id' => $match->id,
            'game_role_id' => $roleA->id,
            'slot_index' => $i,
            'sort_order' => 0,
        ]);
    }
    for ($i = 0; $i < 2; $i++) {
        MatchSlot::factory()->create([
            'match_id' => $match->id,
            'game_role_id' => $roleB->id,
            'slot_index' => $i,
            'sort_order' => 1,
        ]);
    }

    $this->get(route('matches.show', $match))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('roleGroups', 2)
                ->has('roleGroups.0.slots', 3)
                ->has('roleGroups.1.slots', 2)
        );
});

// ───── Privacy gate ────────────────────────────────────────────────────────

it('occupant displayName is null when show_match_history=false (privacy strip)', function (): void {
    [$match, $role] = buildShowFixture();

    // Occupant user with privacy show_match_history=false.
    $occupantUser = User::factory()->create(['username' => 'hidden_player']);
    $occupantPlayer = Player::factory()->for($occupantUser)->create([
        'display_name' => 'Hidden Hero',
    ]);
    PlayerPrivacy::factory()->for($occupantPlayer)->create([
        'show_to' => 'public',
        'show_match_history' => false,
    ]);

    // Occupy slot 0.
    MatchSlot::where('match_id', $match->id)
        ->where('game_role_id', $role->id)
        ->where('slot_index', 0)
        ->update(['occupant_user_id' => $occupantUser->id, 'confirmed_at' => now()]);

    $this->get(route('matches.show', $match))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('roleGroups.0.slots.0.displayName', null)
                ->where('roleGroups.0.slots.0.playerSlug', null)
        );
});

it('occupant clanTag is non-null even when displayName is withheld (D-008 — clan tags always public)', function (): void {
    [$match, $role] = buildShowFixture();

    // Occupant with privacy stripped + active clan with tag.
    $occupantUser = User::factory()->create();
    $occupantPlayer = Player::factory()->for($occupantUser)->create();
    PlayerPrivacy::factory()->for($occupantPlayer)->create([
        'show_to' => 'public',
        'show_match_history' => false,
    ]);

    $clan = Clan::factory()->create(['tag' => 'NPS', 'status' => 'active']);
    $eu = ClanTag::factory()->create(['slug' => 'eu']);
    $clan->tags()->attach($eu);
    ClanMembership::factory()->create([
        'user_id' => $occupantUser->id,
        'clan_id' => $clan->id,
        'left_at' => null,
    ]);

    MatchSlot::where('match_id', $match->id)
        ->where('game_role_id', $role->id)
        ->where('slot_index', 0)
        ->update(['occupant_user_id' => $occupantUser->id, 'confirmed_at' => now()]);

    $this->get(route('matches.show', $match))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('roleGroups.0.slots.0.displayName', null)
                ->where('roleGroups.0.slots.0.clanTag', 'NPS')
        );
});

it('occupant displayName is shown when privacy permits', function (): void {
    [$match, $role] = buildShowFixture();

    $occupantUser = User::factory()->create(['username' => 'visible_player']);
    $occupantPlayer = Player::factory()->for($occupantUser)->create([
        'display_name' => 'Visible Hero',
    ]);
    PlayerPrivacy::factory()->for($occupantPlayer)->create([
        'show_to' => 'public',
        'show_match_history' => true,
    ]);

    MatchSlot::where('match_id', $match->id)
        ->where('game_role_id', $role->id)
        ->where('slot_index', 0)
        ->update(['occupant_user_id' => $occupantUser->id, 'confirmed_at' => now()]);

    $this->get(route('matches.show', $match))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('roleGroups.0.slots.0.displayName', 'Visible Hero')
        );
});

// ───── viewerSlotId + signupAllowed ────────────────────────────────────────

it('viewerSlotId is set when viewer is already signed up to a slot', function (): void {
    [$match, $role] = buildShowFixture();

    $viewer = User::factory()->create();
    Player::factory()->for($viewer)->create();

    $slot = MatchSlot::where('match_id', $match->id)
        ->where('game_role_id', $role->id)
        ->where('slot_index', 0)
        ->first();
    $slot->update(['occupant_user_id' => $viewer->id, 'confirmed_at' => now()]);

    $this->actingAs($viewer)
        ->get(route('matches.show', $match))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('viewerSlotId', $slot->id)
        );
});

it('signupAllowed=false when viewer is guest', function (): void {
    [$match] = buildShowFixture();

    $this->get(route('matches.show', $match))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('signupAllowed', false));
});

it('signupAllowed=false when match.status is locked', function (): void {
    [$match] = buildShowFixture(status: 'locked');

    $viewer = User::factory()->create();
    $this->actingAs($viewer)
        ->get(route('matches.show', $match))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('signupAllowed', false));
});

it('signupAllowed=true when match.status=open AND viewer is auth AND no access rules', function (): void {
    [$match] = buildShowFixture();
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('matches.show', $match))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('signupAllowed', true));
});

it('signupAllowed=false when viewer already occupies a slot in this match (idempotency)', function (): void {
    [$match, $role] = buildShowFixture();

    $viewer = User::factory()->create();
    MatchSlot::where('match_id', $match->id)
        ->where('game_role_id', $role->id)
        ->where('slot_index', 0)
        ->update(['occupant_user_id' => $viewer->id, 'confirmed_at' => now()]);

    $this->actingAs($viewer)
        ->get(route('matches.show', $match))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('signupAllowed', false));
});
