<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\GameMatch;
use App\Models\MatchSlot;
use App\Models\User;
use App\Notifications\MatchStartingSoon;
use App\Services\NotificationDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/*
| Source: .planning/phases/09-polish/09-04-PLAN.md task 1.
|
| Plan-vs-reality drift resolved in 09-04 (D-09-04-A):
|   - GameMatch exposes slots()/MatchSlot.occupant_user_id (NOT signups()).
|   - Clan exposes activeMembers() (NOT activeMemberships()).
|   - Match status enum is draft|open|locked|played|cancelled — there is NO
|     'scheduled' status on the matches table (that's Article). The dispatcher
|     filters on whereIn('status', ['open','locked']).
|
| Asserts SC-1 (dispatcher cron) — fires at T-60min and T-15min for bookable
| matches across signed-up players + active host-clan members, deduped by user.
*/

it('dispatches MatchStartingSoon to signed-up players when match is T-60min away', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-05-14 12:00:00');

    $match = GameMatch::factory()->create([
        'status' => 'open',
        'scheduled_at' => Carbon::now()->addMinutes(60),
    ]);

    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $match->id, 'occupant_user_id' => $u1->id]);
    MatchSlot::factory()->create(['match_id' => $match->id, 'occupant_user_id' => $u2->id]);

    app(NotificationDispatcher::class)->sweepUpcoming();

    Notification::assertSentTo($u1, MatchStartingSoon::class, function (MatchStartingSoon $n): bool {
        return $n->minutesUntilStart === 60;
    });
    Notification::assertSentTo($u2, MatchStartingSoon::class, function (MatchStartingSoon $n): bool {
        return $n->minutesUntilStart === 60;
    });
});

it('dispatches MatchStartingSoon when match is T-15min away', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-05-14 12:00:00');

    $match = GameMatch::factory()->create([
        'status' => 'open',
        'scheduled_at' => Carbon::now()->addMinutes(15),
    ]);
    $user = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $match->id, 'occupant_user_id' => $user->id]);

    app(NotificationDispatcher::class)->sweepUpcoming();

    Notification::assertSentTo($user, MatchStartingSoon::class, function (MatchStartingSoon $n): bool {
        return $n->minutesUntilStart === 15;
    });
});

it('skips matches outside the ±3min boundary of each window', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-05-14 12:00:00');

    // 64 minutes out — outside the ±3min slack on the 60-min window (60+3=63 max).
    $tooFar = GameMatch::factory()->create([
        'status' => 'open',
        'scheduled_at' => Carbon::now()->addMinutes(64),
    ]);
    $userFar = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $tooFar->id, 'occupant_user_id' => $userFar->id]);

    // 62 minutes out — INSIDE the ±3min slack on the 60-min window.
    $inWindow = GameMatch::factory()->create([
        'status' => 'open',
        'scheduled_at' => Carbon::now()->addMinutes(62),
    ]);
    $userIn = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $inWindow->id, 'occupant_user_id' => $userIn->id]);

    app(NotificationDispatcher::class)->sweepUpcoming();

    Notification::assertSentTo($userIn, MatchStartingSoon::class);
    Notification::assertNotSentTo($userFar, MatchStartingSoon::class);
});

it('skips matches whose status is not open or locked', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-05-14 12:00:00');

    $cancelled = GameMatch::factory()->create([
        'status' => 'cancelled',
        'scheduled_at' => Carbon::now()->addMinutes(60),
    ]);
    $userC = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $cancelled->id, 'occupant_user_id' => $userC->id]);

    $draft = GameMatch::factory()->create([
        'status' => 'draft',
        'scheduled_at' => Carbon::now()->addMinutes(60),
    ]);
    $userD = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $draft->id, 'occupant_user_id' => $userD->id]);

    $played = GameMatch::factory()->create([
        'status' => 'played',
        'scheduled_at' => Carbon::now()->addMinutes(60),
    ]);
    $userP = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $played->id, 'occupant_user_id' => $userP->id]);

    app(NotificationDispatcher::class)->sweepUpcoming();

    Notification::assertNotSentTo($userC, MatchStartingSoon::class);
    Notification::assertNotSentTo($userD, MatchStartingSoon::class);
    Notification::assertNotSentTo($userP, MatchStartingSoon::class);
});

it('includes signed-up players AND active host-clan members deduped by user id', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-05-14 12:00:00');

    $clan = Clan::factory()->create();
    $match = GameMatch::factory()->create([
        'status' => 'open',
        'scheduled_at' => Carbon::now()->addMinutes(60),
        'host_clan_id' => $clan->id,
    ]);

    // Player only — signed up but not a clan member.
    $signupOnly = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $match->id, 'occupant_user_id' => $signupOnly->id]);

    // Active member of host clan only — never signed up.
    $memberOnly = User::factory()->create();
    ClanMembership::factory()->create(['clan_id' => $clan->id, 'user_id' => $memberOnly->id]);

    // Both signed up AND active member — must receive exactly ONE notification.
    $both = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $match->id, 'occupant_user_id' => $both->id]);
    ClanMembership::factory()->create(['clan_id' => $clan->id, 'user_id' => $both->id]);

    // Former member of host clan — left_at is set, must NOT receive.
    $former = User::factory()->create();
    ClanMembership::factory()->create([
        'clan_id' => $clan->id,
        'user_id' => $former->id,
        'left_at' => Carbon::now()->subDay(),
    ]);

    app(NotificationDispatcher::class)->sweepUpcoming();

    Notification::assertSentTo($signupOnly, MatchStartingSoon::class);
    Notification::assertSentTo($memberOnly, MatchStartingSoon::class);
    Notification::assertSentToTimes($both, MatchStartingSoon::class, 1);
    Notification::assertNotSentTo($former, MatchStartingSoon::class);
});

it('skips empty slots (occupant_user_id is null)', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-05-14 12:00:00');

    $match = GameMatch::factory()->create([
        'status' => 'open',
        'scheduled_at' => Carbon::now()->addMinutes(60),
    ]);

    // One empty slot, one filled slot — only the filled one's user gets notified.
    MatchSlot::factory()->create(['match_id' => $match->id, 'occupant_user_id' => null]);
    $filled = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $match->id, 'occupant_user_id' => $filled->id]);

    app(NotificationDispatcher::class)->sweepUpcoming();

    Notification::assertSentTo($filled, MatchStartingSoon::class);
    // Empty slot does NOT trigger a notify on a non-existent user — pure noop.
});

it('writes notifications.match.starting_soon rows with the correct data shape (no Notification fake)', function (): void {
    Carbon::setTestNow('2026-05-14 12:00:00');

    $match = GameMatch::factory()->create([
        'status' => 'open',
        'scheduled_at' => Carbon::now()->addMinutes(60),
    ]);
    $user = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $match->id, 'occupant_user_id' => $user->id]);

    app(NotificationDispatcher::class)->sweepUpcoming();

    $rows = DB::table('notifications')
        ->where('type', 'match.starting_soon')
        ->where('notifiable_id', $user->id)
        ->get();

    expect($rows)->toHaveCount(1);

    $row = $rows->first();
    /** @var stdClass $row */
    /** @var array<string, mixed> $data */
    $data = json_decode($row->data, true);
    expect($data['match_id'])->toBe($match->id);
    expect($data['minutes'])->toBe(60);
});
