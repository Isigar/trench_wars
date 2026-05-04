<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;

/*
| Source: 01-09-PLAN.md Task 3 + 01-VALIDATION.md (DiscordOAuthTest entry).
|
| These tests cover GET /auth/discord/redirect (real Socialite driver) and
| GET /auth/discord/callback (Mockery-backed Socialite facade) using the
| `Socialite::shouldReceive` pattern. Real Discord OAuth round-trip is the
| manual smoke gate per VALIDATION.md "Items requiring real-world verification".
*/

beforeEach(function (): void {
    // The Socialite Discord provider needs a non-empty client_id at runtime to
    // build the authorize URL. Real values are loaded from .env in dev/CI;
    // tests just need a placeholder.
    config()->set('services.discord.client_id', 'test-client-id');
    config()->set('services.discord.client_secret', 'test-client-secret');
    config()->set('services.discord.redirect', 'http://localhost/auth/discord/callback');
});

it('redirects to Discord on /auth/discord/redirect', function (): void {
    $response = $this->get('/auth/discord/redirect');

    $response->assertRedirect();
    $location = (string) $response->headers->get('Location');
    expect($location)->toContain('discord.com');
    expect($location)->toContain('client_id=test-client-id');
    expect($location)->toContain('scope=identify+email');
});

it('logs the user in and redirects to / on successful callback', function (): void {
    $fakeUser = (new SocialiteUser)->map([
        'id' => '111222333444555666',
        'nickname' => 'commander_one',
        'name' => 'Commander One',
        'email' => 'commander@example.com',
        'avatar' => 'https://cdn.discordapp.com/avatars/111/abc.png',
        'user' => ['locale' => 'en'],
    ]);

    Socialite::shouldReceive('driver')->with('discord')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($fakeUser);

    $response = $this->get('/auth/discord/callback');

    $response->assertRedirect(route('home'));
    expect(auth()->check())->toBeTrue();
    /** @var User $authenticated */
    $authenticated = auth()->user();
    expect($authenticated->discord_id)->toBe('111222333444555666');
});

it('redirects with cancellation message when Discord OAuth state is invalid', function (): void {
    Socialite::shouldReceive('driver')->with('discord')->andReturnSelf();
    Socialite::shouldReceive('user')->andThrow(new InvalidStateException);

    $response = $this->get('/auth/discord/callback');

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error');
});
