<?php

declare(strict_types=1);

/*
| Public player directory index (GET /players).
|
| Closes the reachability gap where the header nav + sitemap linked /players but
| no route existed (404). Asserts the page resolves, lists players, applies the
| D-018 privacy gate (private-tier hidden from anonymous, visible to self), and
| supports name search.
*/

use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the Players/Index page for an anonymous visitor (no longer 404)', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->for($user)->create(['display_name' => 'Public Player', 'slug' => 'public-player']);
    PlayerPrivacy::factory()->for($player)->create(['show_to' => 'public']);

    $this->get('/players')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Players/Index', false)
                ->has('players', 1)
                ->where('players.0.slug', 'public-player')
                ->where('players.0.displayName', 'Public Player')
                ->has('pagination')
        );
});

it('hides a private-tier player from an anonymous visitor but shows public ones', function (): void {
    $pubUser = User::factory()->create();
    $pub = Player::factory()->for($pubUser)->create(['display_name' => 'Visible', 'slug' => 'visible']);
    PlayerPrivacy::factory()->for($pub)->create(['show_to' => 'public']);

    $privUser = User::factory()->create();
    $priv = Player::factory()->for($privUser)->create(['display_name' => 'Hidden', 'slug' => 'hidden']);
    PlayerPrivacy::factory()->for($priv)->create(['show_to' => 'private']);

    $this->get('/players')
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('players', 1)
                ->where('players.0.slug', 'visible')
        );
});

it('shows a private-tier player to themselves (own-profile bypass)', function (): void {
    $user = User::factory()->create();
    $player = Player::factory()->for($user)->create(['display_name' => 'Me', 'slug' => 'me']);
    PlayerPrivacy::factory()->for($player)->create(['show_to' => 'private']);

    $this->actingAs($user)
        ->get('/players')
        ->assertInertia(fn (Assert $page) => $page->has('players', 1)->where('players.0.slug', 'me'));
});

it('filters by name via ?q=', function (): void {
    foreach (['Alpha', 'Bravo'] as $name) {
        $u = User::factory()->create();
        $p = Player::factory()->for($u)->create(['display_name' => $name, 'slug' => strtolower($name)]);
        PlayerPrivacy::factory()->for($p)->create(['show_to' => 'public']);
    }

    $this->get('/players?q=Alph')
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('players', 1)
                ->where('players.0.displayName', 'Alpha')
                ->where('activeSearch', 'Alph')
        );
});
