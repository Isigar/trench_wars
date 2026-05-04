<?php

declare(strict_types=1);

/*
| Source: 01-VALIDATION.md (InertiaSharedPropsTest stub) — partial coverage.
|
| This plan ships the auth + flash + ziggy props. Plan 08 will extend with
| translations + locale assertions; plan 09 with Discord OAuth login flow.
*/

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the Home page via Inertia', function (): void {
    $this->get('/')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('auth')
                ->has('flash')
                ->has('ziggy.routes')
        );
});

it('does not include a CSRF meta tag in the root view', function (): void {
    // Pitfall 3 mitigation: Inertia handles XSRF via cookie automatically.
    $response = $this->get('/');
    expect($response->getContent())->not->toContain('name="csrf-token"');
});

it('shares auth as null for guests (WR-03 PHP↔TS shape pin)', function (): void {
    $this->get('/')
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('auth', null)
        );
});

it('shares auth as a flat user object for authenticated users (WR-03)', function (): void {
    $this->seed(PermissionSeeder::class);
    $user = User::factory()->create([
        'discord_id' => '123456789012345678',
        'username' => 'commander_one',
        'avatar_url' => 'https://cdn.discordapp.com/avatars/123/abc.png',
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertInertia(
            fn (Assert $page) => $page
                // The auth prop is the user object DIRECTLY — not nested under
                // an `auth.user` envelope. This must stay in sync with the
                // TypeScript declaration in resources/js/types/inertia.d.ts.
                ->has(
                    'auth',
                    fn (Assert $auth) => $auth
                        ->where('id', $user->id)
                        ->where('discord_id', '123456789012345678')
                        ->where('username', 'commander_one')
                        ->where('avatar_url', 'https://cdn.discordapp.com/avatars/123/abc.png')
                )
                ->missing('auth.user')
        );
});
