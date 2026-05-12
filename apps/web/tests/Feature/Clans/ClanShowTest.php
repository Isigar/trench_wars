<?php

declare(strict_types=1);

/*
| Source: 02-07-PLAN.md Task 2 — replaces Wave 0 stub.
| Covers REQ-tenancy-multi-clan: GET /clans/{slug} returns 200 without auth.
| See .planning/phases/02-clans-tags/02-VALIDATION.md Per-Task Verification Map.
*/

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\ClanTag;
use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders ClanData prop with tags + active_member_count', function (): void {
    $clan = Clan::factory()->create(['status' => 'active']);
    $tag1 = ClanTag::factory()->create();
    $tag2 = ClanTag::factory()->create();
    $clan->tags()->attach([$tag1->id, $tag2->id]);

    // Create 5 members (each with a user + player + privacy allowing show_clan_history)
    for ($i = 0; $i < 5; $i++) {
        $user = User::factory()->create();
        $player = Player::factory()->for($user)->create();
        PlayerPrivacy::factory()->for($player)->create(['show_clan_history' => true]);
        ClanMembership::factory()->create(['clan_id' => $clan->id, 'user_id' => $user->id, 'left_at' => null]);
    }

    $this->get("/clans/{$clan->slug}")
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Clans/Show')
                ->where('clan.slug', $clan->slug)
                ->where('clan.active_member_count', 5)
                ->has('clan.tags', 2)
                ->has('members', 5)
                ->where('hiddenMemberCount', 0)
        );
});

it('roster filters out members whose privacy.show_clan_history=false', function (): void {
    $clan = Clan::factory()->create(['status' => 'active']);

    // 2 visible members
    for ($i = 0; $i < 2; $i++) {
        $user = User::factory()->create();
        $player = Player::factory()->for($user)->create();
        PlayerPrivacy::factory()->for($player)->create(['show_clan_history' => true]);
        ClanMembership::factory()->create(['clan_id' => $clan->id, 'user_id' => $user->id, 'left_at' => null]);
    }

    // 1 hidden member
    $hiddenUser = User::factory()->create();
    $hiddenPlayer = Player::factory()->for($hiddenUser)->create();
    PlayerPrivacy::factory()->for($hiddenPlayer)->create(['show_clan_history' => false]);
    ClanMembership::factory()->create(['clan_id' => $clan->id, 'user_id' => $hiddenUser->id, 'left_at' => null]);

    $this->get("/clans/{$clan->slug}")
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Clans/Show')
                ->has('members', 2)
                ->where('hiddenMemberCount', 1)
        );
});

it('roster respects all-private case', function (): void {
    $clan = Clan::factory()->create(['status' => 'active']);

    // 3 hidden members
    for ($i = 0; $i < 3; $i++) {
        $user = User::factory()->create();
        $player = Player::factory()->for($user)->create();
        PlayerPrivacy::factory()->for($player)->create(['show_clan_history' => false]);
        ClanMembership::factory()->create(['clan_id' => $clan->id, 'user_id' => $user->id, 'left_at' => null]);
    }

    $this->get("/clans/{$clan->slug}")
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Clans/Show')
                ->has('members', 0)
                ->where('hiddenMemberCount', 3)
        );
});
