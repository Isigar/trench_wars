<?php

declare(strict_types=1);

use App\Exceptions\TagRestrictedException;
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

/*
| Source: 04-06-PLAN.md Task 3 — replaces Wave 0 RED stub.
|
| Covers Pattern 5 tag-access allowlist semantics at the service layer
| (SC-5 first half — admin-config tag restriction enforcement; SC-5
| second half = controller-level 422 response in plan 04-10).
|
| Allowlist enumeration:
|   - Zero match_access_rules            → open to all (any user)
|   - >=1 rule + user's active clan has  → allowed (intersection non-empty)
|     at least one allowlisted tag
|   - >=1 rule + user has no active clan → blocked (TagRestrictedException)
|   - >=1 rule + user clan tags disjoint → blocked (TagRestrictedException)
|
| The concurrency edge (parallel signups) lives in MatchSignupConcurrencyTest;
| the cheap-first guard order (status > capacity > idempotency > tag) lives
| in MatchSignupServiceTest. This file is focused on Pattern 5 enumeration.
|
| Path note: this file is at tests/Feature/Matches/ (NOT Services/) because
| the Wave 0 stub from plan 04-01 was created there. Both surfaces of plan
| 04-06 (Service guard + Controller 422) share the Matches/ tree.
|
| NAMING NOTE (D-04-03-A): the Match model is `GameMatch`. Tests import
| `use App\Models\GameMatch;` directly per D-04-04-C / D-04-05-B.
*/

/**
 * Build a same-game (match, role, slot) fixture with status='open' and
 * `slotCapacity` empty slots ready. Returns [$match, $role].
 *
 * @return array{0: GameMatch, 1: GameRole}
 */
function buildTagFixture(int $slotCapacity = 2): array
{
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create();
    $match = GameMatch::factory()->for($matchType, 'gameMatchType')->create(['status' => 'open']);

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
 * Attach `$user` to a freshly-built clan tagged with each ClanTag in `$tags`.
 * Creates a single active ClanMembership (D-009 invariant).
 */
function attachUserToClanWithTags(User $user, ClanTag ...$tags): Clan
{
    $clan = Clan::factory()->create(['status' => 'active']);
    foreach ($tags as $tag) {
        $clan->tags()->attach($tag);
    }
    ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clan->id,
        'left_at' => null,
    ]);

    return $clan;
}

// ---------------------------------------------------------------------------
// Open semantics — zero rules = open to all
// ---------------------------------------------------------------------------

it('allows signup when match has zero access rules — empty equals open semantics', function (): void {
    [$match, $role] = buildTagFixture();
    // User has NO active clan — still allowed because rules are empty.
    $user = User::factory()->create();

    $slot = app(MatchSignupService::class)->signup($match, $user, $role);

    expect($slot->occupant_user_id)->toBe($user->id);
});

it('allows signup when match has zero rules AND user has an active clan', function (): void {
    [$match, $role] = buildTagFixture();
    $user = User::factory()->create();
    $tag = ClanTag::factory()->create(['slug' => 'na']);
    attachUserToClanWithTags($user, $tag);

    $slot = app(MatchSignupService::class)->signup($match, $user, $role);

    expect($slot->occupant_user_id)->toBe($user->id);
});

// ---------------------------------------------------------------------------
// Allow paths — user's clan carries at least one allowlisted tag
// ---------------------------------------------------------------------------

it('allows signup when user clan has an allowed tag', function (): void {
    [$match, $role] = buildTagFixture();
    $tag = ClanTag::factory()->create(['slug' => 'eu']);
    MatchAccessRule::factory()->create([
        'match_id' => $match->id,
        'clan_tag_id' => $tag->id,
    ]);

    $user = User::factory()->create();
    attachUserToClanWithTags($user, $tag);

    $slot = app(MatchSignupService::class)->signup($match, $user, $role);

    expect($slot->occupant_user_id)->toBe($user->id);
});

it('allows signup when user clan has at least one allowed tag among many', function (): void {
    [$match, $role] = buildTagFixture();
    $tagEu = ClanTag::factory()->create(['slug' => 'eu']);
    $tagTier1 = ClanTag::factory()->create(['slug' => 'tier-1']);
    MatchAccessRule::factory()->create([
        'match_id' => $match->id,
        'clan_tag_id' => $tagEu->id,
    ]);
    MatchAccessRule::factory()->create([
        'match_id' => $match->id,
        'clan_tag_id' => $tagTier1->id,
    ]);

    // User's clan carries ['na', 'eu'] — 'eu' intersects the rule set.
    $user = User::factory()->create();
    $tagNa = ClanTag::factory()->create(['slug' => 'na']);
    attachUserToClanWithTags($user, $tagNa, $tagEu);

    $slot = app(MatchSignupService::class)->signup($match, $user, $role);

    expect($slot->occupant_user_id)->toBe($user->id);
});

// ---------------------------------------------------------------------------
// Block paths — user's clan does not intersect the allowlist
// ---------------------------------------------------------------------------

it('blocks signup when user clan has no allowed tag', function (): void {
    [$match, $role] = buildTagFixture();
    $tagEu = ClanTag::factory()->create(['slug' => 'eu']);
    MatchAccessRule::factory()->create([
        'match_id' => $match->id,
        'clan_tag_id' => $tagEu->id,
    ]);

    // User's clan tagged only 'na' — disjoint with rule set ['eu'].
    $user = User::factory()->create();
    $tagNa = ClanTag::factory()->create(['slug' => 'na']);
    attachUserToClanWithTags($user, $tagNa);

    expect(fn () => app(MatchSignupService::class)->signup($match, $user, $role))
        ->toThrow(TagRestrictedException::class);
});

it('blocks signup when user has no active clan and rules exist', function (): void {
    [$match, $role] = buildTagFixture();
    $tag = ClanTag::factory()->create(['slug' => 'eu']);
    MatchAccessRule::factory()->create([
        'match_id' => $match->id,
        'clan_tag_id' => $tag->id,
    ]);

    // User has zero ClanMemberships — activeClanMembership returns null.
    $user = User::factory()->create();

    expect(fn () => app(MatchSignupService::class)->signup($match, $user, $role))
        ->toThrow(TagRestrictedException::class);
});

it('blocks signup when user clan exists but carries zero tags and rules exist', function (): void {
    [$match, $role] = buildTagFixture();
    $tag = ClanTag::factory()->create(['slug' => 'eu']);
    MatchAccessRule::factory()->create([
        'match_id' => $match->id,
        'clan_tag_id' => $tag->id,
    ]);

    // User's clan has NO tags — intersection with rule set is empty.
    $user = User::factory()->create();
    attachUserToClanWithTags($user); // no tags

    expect(fn () => app(MatchSignupService::class)->signup($match, $user, $role))
        ->toThrow(TagRestrictedException::class);
});

it('blocks signup with the localized tag_restricted message', function (): void {
    [$match, $role] = buildTagFixture();
    $tag = ClanTag::factory()->create(['slug' => 'eu']);
    MatchAccessRule::factory()->create([
        'match_id' => $match->id,
        'clan_tag_id' => $tag->id,
    ]);
    $user = User::factory()->create();

    expect(fn () => app(MatchSignupService::class)->signup($match, $user, $role))
        ->toThrow(
            TagRestrictedException::class,
            'This match is restricted to clans with specific tags. Your clan does not qualify.'
        );
});

// ---------------------------------------------------------------------------
// Inactive-membership semantics — left_at !== null does NOT count as active
// ---------------------------------------------------------------------------

it('treats a left clan (left_at !== null) as no active clan when rules exist', function (): void {
    [$match, $role] = buildTagFixture();
    $tag = ClanTag::factory()->create(['slug' => 'eu']);
    MatchAccessRule::factory()->create([
        'match_id' => $match->id,
        'clan_tag_id' => $tag->id,
    ]);

    // User WAS in an 'eu'-tagged clan but left — activeClanMembership null.
    $user = User::factory()->create();
    $clan = Clan::factory()->create(['status' => 'active']);
    $clan->tags()->attach($tag);
    ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clan->id,
        'left_at' => now()->subDay(),
    ]);

    expect(fn () => app(MatchSignupService::class)->signup($match, $user, $role))
        ->toThrow(TagRestrictedException::class);
});
