<?php

declare(strict_types=1);

use App\Models\GameMatch;
use App\Models\MatchSlot;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| Source: .planning/phases/09-polish/09-04-PLAN.md task 1 +
|         09-RESEARCH.md § Pitfall 5 (read-then-write dedup race).
|
| Asserts that NotificationDispatcher::alreadyDispatched() correctly dedupes
| against (type, data->match_id, data->minutes), and that the T-60 + T-15
| windows do NOT collide on the same key (separate `minutes` value).
*/

it('does not duplicate notifications when sweep runs twice in the same window', function (): void {
    Carbon::setTestNow('2026-05-14 12:00:00');

    $match = GameMatch::factory()->create([
        'status' => 'open',
        'scheduled_at' => Carbon::now()->addMinutes(60),
    ]);
    $user = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $match->id, 'occupant_user_id' => $user->id]);

    app(NotificationDispatcher::class)->sweepUpcoming();
    app(NotificationDispatcher::class)->sweepUpcoming();

    $count = DB::table('notifications')
        ->where('notifiable_id', $user->id)
        ->where('type', 'match.starting_soon')
        ->whereJsonContains('data->match_id', $match->id)
        ->whereJsonContains('data->minutes', 60)
        ->count();

    expect($count)->toBe(1);
});

it('treats T-60 and T-15 as separate dispatch keys (a single match in both windows produces 2 rows)', function (): void {
    Carbon::setTestNow('2026-05-14 12:00:00');

    // First sweep: match is at T-60min — fires the 60-min notification but not
    // the 15-min one (scheduled_at is 60min away, not 15min away).
    $match = GameMatch::factory()->create([
        'status' => 'open',
        'scheduled_at' => Carbon::now()->addMinutes(60),
    ]);
    $user = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $match->id, 'occupant_user_id' => $user->id]);

    app(NotificationDispatcher::class)->sweepUpcoming();

    // Advance time so the same match is now at T-15min.
    Carbon::setTestNow(Carbon::parse('2026-05-14 12:00:00')->addMinutes(45));

    app(NotificationDispatcher::class)->sweepUpcoming();

    $rows = DB::table('notifications')
        ->where('notifiable_id', $user->id)
        ->where('type', 'match.starting_soon')
        ->orderBy('created_at')
        ->get();

    expect($rows)->toHaveCount(2);

    /** @var array<int, int> $minutesSet */
    $minutesSet = $rows
        ->map(fn ($r): int => json_decode($r->data, true)['minutes'])
        ->sort()
        ->values()
        ->all();
    expect($minutesSet)->toBe([15, 60]);
});

it('reuses the alreadyDispatched dedupe key on (type, data->match_id, data->minutes)', function (): void {
    Carbon::setTestNow('2026-05-14 12:00:00');

    $match = GameMatch::factory()->create([
        'status' => 'open',
        'scheduled_at' => Carbon::now()->addMinutes(60),
    ]);
    $user = User::factory()->create();
    MatchSlot::factory()->create(['match_id' => $match->id, 'occupant_user_id' => $user->id]);

    // Manually pre-seed a notification row for this (user, match, minutes=60)
    // tuple — simulates a prior sweep that already dispatched. The
    // alreadyDispatched() query must find this row and the next sweep must
    // skip the user.
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'match.starting_soon',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'data' => json_encode([
            'match_id' => $match->id,
            'minutes' => 60,
            'i18n_key' => 'notifications.match_starting_soon.title',
        ]),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(NotificationDispatcher::class)->sweepUpcoming();

    $count = DB::table('notifications')
        ->where('notifiable_id', $user->id)
        ->where('type', 'match.starting_soon')
        ->whereJsonContains('data->match_id', $match->id)
        ->whereJsonContains('data->minutes', 60)
        ->count();

    // Still exactly 1 — the manually inserted row, NOT a second sweep-written one.
    expect($count)->toBe(1);
});
