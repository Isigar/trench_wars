<?php

declare(strict_types=1);

/*
| Source: .planning/phases/09-polish/09-11-PLAN.md task 2 — turns the Wave 0
| stub (plan 09-01) GREEN.
|
| Covers SC-5 (report abuse throttle) — `report-abuse` named limiter caps
| each authenticated user at 5 reports per hour. The 6th in-window report
| returns 429 (T-09-11-03 mitigation; bounds report-storm at the per-user
| layer because anonymous reports are NOT supported v1).
|
| The test fires REAL POSTs through the auth + throttle middleware stack,
| not a contrived limiter unit-test, so the wiring in routes/web.php is
| exercised end-to-end (Pitfall 8 — assert what users observe, not what the
| code says).
*/

use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    // Clear any prior per-user buckets so the in-test reporter starts fresh.
    // Keys follow AppServiceProvider::boot — 'user:<uuid>' for report-abuse.
    // Tests don't persist users across runs, but a previous test's auto-
    // generated UUID may still have a residual hit count if the cache is
    // not flushed (RefreshDatabase flushes Postgres, NOT the cache store).
    RateLimiter::clear('ip:127.0.0.1');
});

it('admits the first 5 reports from a single user within one hour', function (): void {
    $reporter = User::factory()->create();
    $targets = Player::factory()->count(5)->create();

    foreach ($targets as $i => $player) {
        $this->actingAs($reporter)->post(route('reports.store'), [
            'target_type' => Player::class,
            'target_id' => $player->id,
            'reason_code' => 'spam',
            'body' => "Report number {$i}.",
        ])->assertRedirect();
    }
});

it('returns 429 on the 6th report from the same user within one hour', function (): void {
    $reporter = User::factory()->create();
    $targets = Player::factory()->count(6)->create();

    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($reporter)->post(route('reports.store'), [
            'target_type' => Player::class,
            'target_id' => $targets[$i]->id,
            'reason_code' => 'harassment',
            'body' => "Report {$i}.",
        ])->assertRedirect();
    }

    // 6th in-window submission MUST hit the limiter (T-09-11-03).
    $this->actingAs($reporter)->post(route('reports.store'), [
        'target_type' => Player::class,
        'target_id' => $targets[5]->id,
        'reason_code' => 'harassment',
        'body' => 'One too many.',
    ])->assertStatus(429);
});

it('keys the report-abuse throttle per user — separate users have separate buckets', function (): void {
    $reporterA = User::factory()->create();
    $reporterB = User::factory()->create();
    $targets = Player::factory()->count(6)->create();

    // Reporter A spends their full 5/hour budget.
    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($reporterA)->post(route('reports.store'), [
            'target_type' => Player::class,
            'target_id' => $targets[$i]->id,
            'reason_code' => 'cheating',
            'body' => "A's report {$i}.",
        ])->assertRedirect();
    }

    // Reporter B's first report MUST succeed — separate bucket.
    $this->actingAs($reporterB)->post(route('reports.store'), [
        'target_type' => Player::class,
        'target_id' => $targets[5]->id,
        'reason_code' => 'cheating',
        'body' => "B's first report.",
    ])->assertRedirect();
});
