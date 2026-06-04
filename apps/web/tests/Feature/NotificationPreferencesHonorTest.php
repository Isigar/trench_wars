<?php

declare(strict_types=1);

/*
 * Source: .planning/phases/12-notifications-bot-polish/12-01-PLAN.md task 2.
 *
 * End-to-end honor test: POST a preference change that disables a channel →
 * assert User::enabledNotificationChannels() excludes that channel AND the
 * matching Notification's via() excludes the mapped channel class.
 *
 * NOTF-01 SC-1: "The dispatcher honors preference changes from that point forward."
 */

use App\Models\GameMatch;
use App\Models\User;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\MatchStartingSoon;

it('disabling discord for match_starting_soon removes it from enabledNotificationChannels', function (): void {
    // Arrange: user with a discord_id so discord is default-ON for match_starting_soon.
    /** @var User $user */
    $user = User::factory()->create(['discord_id' => '123456789012345678']);

    // Confirm default includes discord.
    expect($user->enabledNotificationChannels('match_starting_soon'))
        ->toContain('discord');

    // Act: POST to the update route disabling discord for match_starting_soon.
    $this->actingAs($user)
        ->post(route('account.notification-preferences.update'), [
            'preferences' => [
                ['event_type' => 'match_starting_soon', 'channel' => 'discord', 'enabled' => false],
            ],
        ])
        ->assertRedirect();

    // Assert: fresh() defeats any in-memory relation cache.
    expect($user->fresh()->enabledNotificationChannels('match_starting_soon'))
        ->not->toContain('discord')
        ->toContain('database');
});

it('notification via() reflects the disabled discord channel for match_starting_soon', function (): void {
    // Arrange: user with discord_id, disable discord for match_starting_soon.
    /** @var User $user */
    $user = User::factory()->create(['discord_id' => '123456789012345678']);

    $this->actingAs($user)
        ->post(route('account.notification-preferences.update'), [
            'preferences' => [
                ['event_type' => 'match_starting_soon', 'channel' => 'discord', 'enabled' => false],
            ],
        ])
        ->assertRedirect();

    // Act: instantiate the MatchStartingSoon notification (no match needed for via()).
    $match = GameMatch::factory()->create();
    $notification = new MatchStartingSoon($match, 15);

    $freshUser = $user->fresh();
    $via = $notification->via($freshUser);

    // Assert: discord channel class is absent; database is present.
    expect($via)
        ->not->toContain(DiscordChannel::class)
        ->toContain('database');
});
