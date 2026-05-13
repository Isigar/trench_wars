<?php

declare(strict_types=1);

/*
| Source: 04-10-PLAN.md Task 2 — replaces Wave 0 RED stub.
|
| Covers SC-2 + SC-5 HTTP entry-point integration (the service-layer guards are
| exhaustively covered in tests/Feature/Services/MatchSignupServiceTest.php and
| tests/Feature/Matches/MatchSignupTagRestrictedTest.php). This file proves the
| controller's 4-exception catch order converts each typed service exception
| into a 422 ValidationException with the correct error key, plus the DELETE
| handler's ownership guards.
|
| 4-exception catch order (and resulting field key):
|   MatchNotOpenException     → general
|   TagRestrictedException    → general
|   AlreadySignedUpException  → general
|   CapacityExceededException → game_role_id
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
use App\Models\MatchAccessRule;
use App\Models\MatchSlot;
use App\Models\User;
use App\Services\MatchSignupService;

/**
 * Build a same-game (match, role) fixture with $slotCapacity empty slots.
 *
 * @return array{0: GameMatch, 1: GameRole}
 */
function buildSignupControllerFixture(int $slotCapacity = 2, string $status = 'open'): array
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

// ═══════════════════════════════════════════════════════════════════════════
// POST /matches/{match}/signups — happy path
// ═══════════════════════════════════════════════════════════════════════════

it('POST /matches/{match}/signups returns redirect with flash success on happy path', function (): void {
    [$match, $role] = buildSignupControllerFixture();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('matches.signups.store', $match), [
            'game_role_id' => $role->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(
        MatchSlot::where('match_id', $match->id)
            ->where('occupant_user_id', $user->id)
            ->exists()
    )->toBeTrue();
});

// ═══════════════════════════════════════════════════════════════════════════
// POST /matches/{match}/signups — auth gate
// ═══════════════════════════════════════════════════════════════════════════

it('POST /matches/{match}/signups guest is redirected to login (auth middleware)', function (): void {
    [$match, $role] = buildSignupControllerFixture();

    $this->post(route('matches.signups.store', $match), [
        'game_role_id' => $role->id,
    ])->assertRedirect('/auth/discord/redirect');
});

// ═══════════════════════════════════════════════════════════════════════════
// POST /matches/{match}/signups — 422 paths (4-exception conversion)
// ═══════════════════════════════════════════════════════════════════════════

it('POST /matches/{match}/signups returns 422 with capacity_full when role is full', function (): void {
    [$match, $role] = buildSignupControllerFixture(slotCapacity: 1);

    // Pre-fill the only slot.
    $first = User::factory()->create();
    app(MatchSignupService::class)->signup($match, $first, $role);

    $second = User::factory()->create();
    $this->actingAs($second)
        ->post(route('matches.signups.store', $match), [
            'game_role_id' => $role->id,
        ])
        ->assertStatus(302) // ValidationException redirects back with session errors
        ->assertSessionHasErrors('game_role_id');
});

it('POST /matches/{match}/signups returns 422 with tag_restricted error when user clan has no allowed tag', function (): void {
    [$match, $role] = buildSignupControllerFixture();

    // Add tag-restriction.
    $allowedTag = ClanTag::factory()->create(['slug' => 'eu']);
    MatchAccessRule::factory()->create([
        'match_id' => $match->id,
        'clan_tag_id' => $allowedTag->id,
    ]);

    // User's clan has a different tag.
    $user = User::factory()->create();
    $clan = Clan::factory()->create(['status' => 'active']);
    $otherTag = ClanTag::factory()->create(['slug' => 'na']);
    $clan->tags()->attach($otherTag);
    ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clan->id,
        'left_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('matches.signups.store', $match), [
            'game_role_id' => $role->id,
        ])
        ->assertStatus(302)
        ->assertSessionHasErrors('general');
});

it('POST /matches/{match}/signups returns 422 with already_signed_up error when user has existing slot', function (): void {
    [$match, $role] = buildSignupControllerFixture();

    $user = User::factory()->create();
    // First signup succeeds via the service.
    app(MatchSignupService::class)->signup($match, $user, $role);

    $this->actingAs($user)
        ->post(route('matches.signups.store', $match), [
            'game_role_id' => $role->id,
        ])
        ->assertStatus(302)
        ->assertSessionHasErrors('general');
});

it('POST /matches/{match}/signups returns 422 with not_open error when match.status != open', function (): void {
    [$match, $role] = buildSignupControllerFixture(status: 'locked');

    $user = User::factory()->create();
    $this->actingAs($user)
        ->post(route('matches.signups.store', $match), [
            'game_role_id' => $role->id,
        ])
        ->assertStatus(302)
        ->assertSessionHasErrors('general');
});

// ═══════════════════════════════════════════════════════════════════════════
// POST /matches/{match}/signups — request validation
// ═══════════════════════════════════════════════════════════════════════════

it('POST /matches/{match}/signups returns 422 when game_role_id is missing', function (): void {
    [$match] = buildSignupControllerFixture();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('matches.signups.store', $match), [])
        ->assertStatus(302)
        ->assertSessionHasErrors('game_role_id');
});

it('POST /matches/{match}/signups returns 422 when game_role_id is not a valid UUID', function (): void {
    [$match] = buildSignupControllerFixture();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('matches.signups.store', $match), [
            'game_role_id' => 'not-a-uuid',
        ])
        ->assertStatus(302)
        ->assertSessionHasErrors('game_role_id');
});

// ═══════════════════════════════════════════════════════════════════════════
// DELETE /matches/{match}/signups/{slot} — cancel signup
// ═══════════════════════════════════════════════════════════════════════════

it('DELETE /matches/{match}/signups/{slot} clears occupant on success', function (): void {
    [$match, $role] = buildSignupControllerFixture();

    $user = User::factory()->create();
    /** @var MatchSlot $slot */
    $slot = app(MatchSignupService::class)->signup($match, $user, $role);

    $this->actingAs($user)
        ->delete(route('matches.signups.destroy', ['match' => $match, 'slot' => $slot]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $fresh = MatchSlot::findOrFail($slot->id);
    expect($fresh->occupant_user_id)->toBeNull();
    expect($fresh->confirmed_at)->toBeNull();
});

it('DELETE /matches/{match}/signups/{slot} returns 403 when slot belongs to another user', function (): void {
    [$match, $role] = buildSignupControllerFixture();

    $owner = User::factory()->create();
    /** @var MatchSlot $slot */
    $slot = app(MatchSignupService::class)->signup($match, $owner, $role);

    $thief = User::factory()->create();
    $this->actingAs($thief)
        ->delete(route('matches.signups.destroy', ['match' => $match, 'slot' => $slot]))
        ->assertStatus(403);

    // Ensure no mutation occurred.
    $fresh = MatchSlot::findOrFail($slot->id);
    expect($fresh->occupant_user_id)->toBe($owner->id);
});

it('DELETE /matches/{match}/signups/{slot} returns 404 when slot belongs to a different match', function (): void {
    [$matchA, $roleA] = buildSignupControllerFixture();
    [$matchB] = buildSignupControllerFixture();

    $user = User::factory()->create();
    /** @var MatchSlot $slotA */
    $slotA = app(MatchSignupService::class)->signup($matchA, $user, $roleA);

    // Hit matchB with slotA — controller should 404 (URL-param mismatch guard).
    $this->actingAs($user)
        ->delete(route('matches.signups.destroy', ['match' => $matchB, 'slot' => $slotA]))
        ->assertStatus(404);
});

it('DELETE /matches/{match}/signups/{slot} guest is redirected (auth middleware)', function (): void {
    [$match, $role] = buildSignupControllerFixture();

    $owner = User::factory()->create();
    /** @var MatchSlot $slot */
    $slot = app(MatchSignupService::class)->signup($match, $owner, $role);

    $this->delete(route('matches.signups.destroy', ['match' => $match, 'slot' => $slot]))
        ->assertRedirect('/auth/discord/redirect');
});

// ═══════════════════════════════════════════════════════════════════════════
// Route registration
// ═══════════════════════════════════════════════════════════════════════════

it('routes are registered with expected names', function (): void {
    $matchId = '00000000-0000-0000-0000-000000000000';
    $slotId = '11111111-1111-1111-1111-111111111111';
    expect(route('matches.signups.store', ['match' => $matchId]))
        ->toBe(url("/matches/{$matchId}/signups"));
    expect(route('matches.signups.destroy', ['match' => $matchId, 'slot' => $slotId]))
        ->toBe(url("/matches/{$matchId}/signups/{$slotId}"));
});
