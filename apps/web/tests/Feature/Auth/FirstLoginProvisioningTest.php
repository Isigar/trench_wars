<?php

declare(strict_types=1);

use App\Listeners\ProvisionFirstLogin;
use App\Models\Player;
use App\Models\PlayerPrivacy;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/*
| Source: 01-09-PLAN.md Task 3 + 01-VALIDATION.md (FirstLoginProvisioningTest).
|
| Verifies the SC-1 contract end-to-end with a mocked Socialite Discord user:
|   - First login creates exactly 1 row each in users + players + player_privacy
|   - D-018 privacy defaults are applied (show_real_name=false, others true,
|     show_to=community)
|   - Re-login is idempotent (no duplicate rows)
|   - last_login_at updates on every login (T-1-06 audit)
*/

function fakeDiscordUser(string $id, string $nick = 'recruit_zero'): SocialiteUser
{
    /** @var SocialiteUser $user */
    $user = (new SocialiteUser)->map([
        'id' => $id,
        'nickname' => $nick,
        'name' => $nick,
        'email' => "{$nick}@example.com",
        'avatar' => null,
        'user' => ['locale' => 'en'],
    ]);

    return $user;
}

beforeEach(function (): void {
    config()->set('services.discord.client_id', 'test-client-id');
    config()->set('services.discord.client_secret', 'test-client-secret');
    config()->set('services.discord.redirect', 'http://localhost/auth/discord/callback');

    Socialite::shouldReceive('driver')->with('discord')->andReturnSelf();
});

it('creates users + players + player_privacy on first login', function (): void {
    Socialite::shouldReceive('user')->andReturn(fakeDiscordUser('999000111'));

    expect(User::count())->toBe(0);
    expect(Player::count())->toBe(0);
    expect(PlayerPrivacy::count())->toBe(0);

    $this->get('/auth/discord/callback')->assertRedirect(route('home'));

    expect(User::count())->toBe(1);
    expect(Player::count())->toBe(1);
    expect(PlayerPrivacy::count())->toBe(1);
});

it('applies D-018 defaults to player_privacy', function (): void {
    Socialite::shouldReceive('user')->andReturn(fakeDiscordUser('999000222'));

    $this->get('/auth/discord/callback');

    $privacy = PlayerPrivacy::firstOrFail();
    expect($privacy->show_to)->toBe('community');
    expect($privacy->show_real_name)->toBeFalse();
    expect($privacy->show_discord_tag)->toBeTrue();
    expect($privacy->show_clan_history)->toBeTrue();
    expect($privacy->show_match_history)->toBeTrue();
    expect($privacy->show_stats)->toBeTrue();
});

it('does NOT create duplicate rows on re-login (idempotent)', function (): void {
    Socialite::shouldReceive('user')->andReturn(fakeDiscordUser('999000333'));

    $this->get('/auth/discord/callback');
    auth()->logout();
    $this->get('/auth/discord/callback');

    expect(User::count())->toBe(1);
    expect(Player::count())->toBe(1);
    expect(PlayerPrivacy::count())->toBe(1);
});

it('absorbs concurrent first-login race without surfacing 500 (WR-01)', function (): void {
    // Real concurrent HTTP would need a parallel test runner; we simulate the
    // race deterministically by invoking the listener twice on a user whose
    // `player` relation is freshly null in memory both times. Without the
    // try/catch around player()->create(), the second invocation throws
    // UniqueConstraintViolationException and bubbles out of Auth::login.
    $user = User::factory()->create(['discord_id' => '777111222']);

    $listener = new ProvisionFirstLogin;

    // First invocation creates player + privacy.
    $listener->handle(new Login('web', $user, false));
    expect(Player::where('user_id', $user->id)->count())->toBe(1);

    // Force the second invocation to *also* see player === null (simulates
    // two web workers racing on the same User row before either has committed).
    $user->setRelation('player', null);

    // Should NOT throw — the unique violation is the expected outcome of the
    // race, and the listener treats it as idempotent success.
    $listener->handle(new Login('web', $user, false));

    // Still exactly one player row — DB UNIQUE held the line.
    expect(Player::where('user_id', $user->id)->count())->toBe(1);
});

it('updates last_login_at on every login', function (): void {
    Socialite::shouldReceive('user')->andReturn(fakeDiscordUser('999000444'));

    $this->get('/auth/discord/callback');
    /** @var User $userAfterFirst */
    $userAfterFirst = User::firstOrFail();
    $first = $userAfterFirst->last_login_at;
    auth()->logout();

    $this->travel(5)->minutes();
    $this->get('/auth/discord/callback');

    /** @var User $userAfterSecond */
    $userAfterSecond = User::firstOrFail()->fresh();
    $second = $userAfterSecond->last_login_at;

    expect($second)->not->toBeNull();
    expect($first)->not->toBeNull();
    expect($second->greaterThan($first))->toBeTrue();
});
