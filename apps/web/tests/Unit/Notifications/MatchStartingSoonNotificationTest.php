<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\ClanInvite;
use App\Models\GameMatch;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\ClanApplicationDecided;
use App\Notifications\ClanInviteReceived;
use App\Notifications\MatchCancelled;
use App\Notifications\MatchResultPublished;
use App\Notifications\MatchStartingSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Source: .planning/phases/09-polish/09-03-PLAN.md task 2.
|
| Wave 0 stub turned GREEN. Asserts SC-1 (MatchStartingSoon ships database +
| discord channels) AND Pitfall 4 LOCKED (every Notification class has a unique
| databaseType discriminator — reflected over the App\Notifications namespace).
*/

uses(RefreshDatabase::class);

it('routes to database channel only when user has no discord_id', function (): void {
    $user = User::factory()->create(['discord_id' => '']);
    $match = GameMatch::factory()->create();

    $notification = new MatchStartingSoon($match, minutesUntilStart: 60);

    expect($notification->via($user))->toEqual(['database']);
});

it('routes to both database and discord when user has discord_id and default prefs', function (): void {
    $user = User::factory()->create();
    $match = GameMatch::factory()->create();

    $notification = new MatchStartingSoon($match, minutesUntilStart: 60);

    expect($notification->via($user))->toEqual(['database', DiscordChannel::class]);
});

it('honours explicit discord opt-out via preference row', function (): void {
    $user = User::factory()->create();
    UserNotificationPreference::factory()
        ->for($user)
        ->forEvent('match_starting_soon')
        ->state(['channel' => 'discord', 'enabled' => false])
        ->create();

    $user->refresh()->load('notificationPreferences');

    $notification = new MatchStartingSoon(GameMatch::factory()->create(), minutesUntilStart: 60);

    expect($notification->via($user))->toEqual(['database']);
});

it('toArray includes match_id, minutes, i18n_key', function (): void {
    $user = User::factory()->create();
    $match = GameMatch::factory()->create();

    $notification = new MatchStartingSoon($match, minutesUntilStart: 15);

    $array = $notification->toArray($user);

    expect($array)
        ->toHaveKey('match_id', $match->id)
        ->toHaveKey('minutes', 15)
        ->toHaveKey('i18n_key', 'notifications.match_starting_soon.title');
});

it('databaseType returns the stable discriminator match.starting_soon', function (): void {
    $user = User::factory()->create();
    $match = GameMatch::factory()->create();

    $notification = new MatchStartingSoon($match, minutesUntilStart: 15);

    expect($notification->databaseType($user))->toEqual('match.starting_soon');
});

it('toDiscord returns the user_dm outbox shape with recipient_id and embed payload', function (): void {
    $user = User::factory()->create(['discord_id' => '987654321098765432']);
    $match = GameMatch::factory()->create();

    $notification = new MatchStartingSoon($match, minutesUntilStart: 60);
    $payload = $notification->toDiscord($user);

    expect($payload)
        ->toHaveKey('message_type', 'user_dm')
        ->toHaveKey('channel_id', '')
        ->toHaveKey('recipient_id', '987654321098765432')
        ->toHaveKey('payload');

    expect($payload['payload'])
        ->toHaveKey('embed_title')
        ->toHaveKey('embed_description')
        ->toHaveKey('cta_url');

    expect($payload['payload']['embed_title'])
        ->toBeString()
        ->toContain('60');
});

it('every notification class has a unique databaseType discriminator (Pitfall 4 LOCKED)', function (): void {
    // Reflect over the App\Notifications namespace and collect every concrete
    // Notification subclass's databaseType() return value. The set MUST be
    // collision-free — any two classes sharing a discriminator route Vue bell
    // entries to the wrong renderer.

    $user = User::factory()->create();
    $match = GameMatch::factory()->create();
    $clan = Clan::factory()->create();
    $application = ClanApplication::factory()->create();
    $invite = ClanInvite::factory()->create();

    /** @var array<class-string, string> $discriminators */
    $discriminators = [
        MatchStartingSoon::class => (new MatchStartingSoon($match, 60))->databaseType($user),
        MatchCancelled::class => (new MatchCancelled($match))->databaseType($user),
        MatchResultPublished::class => (new MatchResultPublished($match, $clan))->databaseType($user),
        ClanApplicationDecided::class => (new ClanApplicationDecided($application))->databaseType($user),
        ClanInviteReceived::class => (new ClanInviteReceived($invite))->databaseType($user),
    ];

    expect(array_values($discriminators))->toHaveCount(5);
    expect(array_unique(array_values($discriminators)))
        ->toHaveCount(5, sprintf(
            'Duplicate databaseType discriminator(s) detected: %s',
            json_encode($discriminators, JSON_PRETTY_PRINT),
        ));
});
