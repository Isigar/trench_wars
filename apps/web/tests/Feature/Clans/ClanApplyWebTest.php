<?php

declare(strict_types=1);

/*
| Source: 10-03-PLAN.md Task 2 — ClanApplyController web feature test.
|
| Covers CLAN-01 requirements for the web endpoint:
|   POST /clans/{clan:slug}/apply
|
| Trust boundaries (from 10-03 threat model):
|   T-10-03-02 — web controller uses $request->user() (session); no client-supplied applicant.
|   T-10-03-03 — all guards live in ClanApplicationService::apply(); controller only delegates.
|
| Test cases:
|   (a) Authenticated eligible user → redirect back + pending ClanApplication exists.
|   (b) Optional message stored; whitespace-only message → null stored.
|   (c) Guard failure (accepts_applications=false) → assertSessionHasErrors(['application']) + no row.
|   (d) Guest POST → redirected toward login (auth middleware).
*/

use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\ClanMembership;
use App\Models\User;

it('authenticated eligible user can apply — pending row created + redirect back', function (): void {
    $clan = Clan::factory()->create([
        'accepts_applications' => true,
        'slug' => 'web-apply-clan',
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('clans.apply', $clan->slug))
        ->assertRedirect();

    expect(
        ClanApplication::where('clan_id', $clan->id)
            ->where('applicant_user_id', $user->id)
            ->where('status', 'pending')
            ->exists()
    )->toBeTrue();
});

it('stores provided message on the application', function (): void {
    $clan = Clan::factory()->create([
        'accepts_applications' => true,
        'slug' => 'web-apply-msg-clan',
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('clans.apply', $clan->slug), ['message' => 'Hello, I want to join!'])
        ->assertRedirect();

    $app = ClanApplication::where('clan_id', $clan->id)
        ->where('applicant_user_id', $user->id)
        ->first();

    expect($app)->not->toBeNull()
        ->and($app->message)->toBe('Hello, I want to join!');
});

it('stores null when message is whitespace-only', function (): void {
    $clan = Clan::factory()->create([
        'accepts_applications' => true,
        'slug' => 'web-apply-ws-clan',
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('clans.apply', $clan->slug), ['message' => '   '])
        ->assertRedirect();

    $app = ClanApplication::where('clan_id', $clan->id)
        ->where('applicant_user_id', $user->id)
        ->first();

    expect($app)->not->toBeNull()
        ->and($app->message)->toBeNull();
});

it('guard failure (accepts_applications=false) returns session error on application key — no row created', function (): void {
    $clan = Clan::factory()->create([
        'accepts_applications' => false,
        'slug' => 'web-apply-closed-clan',
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('clans.apply', $clan->slug))
        ->assertSessionHasErrors(['application']);

    expect(
        ClanApplication::where('clan_id', $clan->id)->count()
    )->toBe(0);
});

it('guard failure (already in clan) returns session error on application key — no row created', function (): void {
    $clan = Clan::factory()->create([
        'accepts_applications' => true,
        'slug' => 'web-apply-alreadymember-clan',
    ]);
    $user = User::factory()->create();

    // User is already an active member of another clan.
    $otherClan = Clan::factory()->create();
    ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $otherClan->id,
        'left_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('clans.apply', $clan->slug))
        ->assertSessionHasErrors(['application']);

    expect(
        ClanApplication::where('clan_id', $clan->id)->count()
    )->toBe(0);
});

it('guest POST is redirected to login (auth middleware)', function (): void {
    $clan = Clan::factory()->create([
        'accepts_applications' => true,
        'slug' => 'web-apply-guest-clan',
    ]);

    $this->post(route('clans.apply', $clan->slug))
        ->assertRedirect();

    expect(
        ClanApplication::where('clan_id', $clan->id)->count()
    )->toBe(0);
});
