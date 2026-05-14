<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Source: .planning/phases/09-polish/09-03-PLAN.md task 1 + task 2 +
|         09-RESEARCH.md Pattern 7 (default-on web, default-off discord without
|         discord_id) + Open Question 3 LOCKED (match_result_published Discord
|         DM is default-OFF even when discord_id present).
|
| Wave 0 stub turned GREEN. Asserts SC-1 (preferences honour user opt-outs per
| notification type) AND Open Question 3 (match-result Discord DM opt-in only).
*/

uses(RefreshDatabase::class);

it('defaults database channel to ON for every event_type', function (): void {
    $user = User::factory()->create();

    expect($user->enabledNotificationChannels('match_starting_soon'))
        ->toContain('database');
    expect($user->enabledNotificationChannels('clan_invite_received'))
        ->toContain('database');
});

it('defaults discord channel to ON when user has discord_id and event is not match_result_published', function (): void {
    $user = User::factory()->create();   // factory always sets discord_id

    expect($user->enabledNotificationChannels('match_starting_soon'))
        ->toEqual(['database', 'discord']);
    expect($user->enabledNotificationChannels('clan_invite_received'))
        ->toEqual(['database', 'discord']);
});

it('omits discord channel when user has an empty discord_id (defensive guard)', function (): void {
    // users.discord_id is NOT NULL by schema (D-002 — Discord OAuth canonical),
    // but the enabledNotificationChannels guard handles the edge case where the
    // column is somehow blank (e.g. seeder + future schema relaxation). An empty
    // string is the closest the current schema admits to "no discord_id".
    $user = User::factory()->create(['discord_id' => '']);

    expect($user->enabledNotificationChannels('match_starting_soon'))
        ->toEqual(['database']);
});

it('defaults match_result_published discord channel to OFF (Open Question 3 LOCKED)', function (): void {
    $user = User::factory()->create();   // discord_id IS present

    expect($user->enabledNotificationChannels('match_result_published'))
        ->toEqual(['database'])
        ->and($user->enabledNotificationChannels('match_result_published'))
        ->not->toContain('discord');
});

it('honours an explicit database opt-out row', function (): void {
    $user = User::factory()->create();
    UserNotificationPreference::factory()
        ->for($user)
        ->forEvent('match_starting_soon')
        ->state(['channel' => 'database', 'enabled' => false])
        ->create();

    $user->refresh()->load('notificationPreferences');

    expect($user->enabledNotificationChannels('match_starting_soon'))
        ->not->toContain('database')
        ->and($user->enabledNotificationChannels('match_starting_soon'))
        ->toEqual(['discord']);
});

it('honours an explicit discord opt-out row', function (): void {
    $user = User::factory()->create();
    UserNotificationPreference::factory()
        ->for($user)
        ->forEvent('match_starting_soon')
        ->state(['channel' => 'discord', 'enabled' => false])
        ->create();

    $user->refresh()->load('notificationPreferences');

    expect($user->enabledNotificationChannels('match_starting_soon'))
        ->toEqual(['database']);
});

it('honours an explicit discord opt-IN for match_result_published (override default-off)', function (): void {
    $user = User::factory()->create();
    UserNotificationPreference::factory()
        ->for($user)
        ->forEvent('match_result_published')
        ->state(['channel' => 'discord', 'enabled' => true])
        ->create();

    $user->refresh()->load('notificationPreferences');

    expect($user->enabledNotificationChannels('match_result_published'))
        ->toEqual(['database', 'discord']);
});

it('returns empty array when every channel is opted out', function (): void {
    $user = User::factory()->create();
    UserNotificationPreference::factory()
        ->for($user)
        ->forEvent('match_starting_soon')
        ->state(['channel' => 'database', 'enabled' => false])
        ->create();
    UserNotificationPreference::factory()
        ->for($user)
        ->forEvent('match_starting_soon')
        ->state(['channel' => 'discord', 'enabled' => false])
        ->create();

    $user->refresh()->load('notificationPreferences');

    expect($user->enabledNotificationChannels('match_starting_soon'))
        ->toEqual([]);
});
