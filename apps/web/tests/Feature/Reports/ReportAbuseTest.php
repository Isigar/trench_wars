<?php

declare(strict_types=1);

/*
| Source: .planning/phases/09-polish/09-11-PLAN.md task 2 — turns the Wave 0
| stub (plan 09-01) GREEN.
|
| Covers SC-5 (report abuse) — POST /reports creates an abuse_reports row +
| writes an activity_log entry. Validates:
|   1. happy path: row + audit log written.
|   2. activity_log row has causer=reporter, subject=target, log='abuse.reported'.
|   3. 404 when target_id does not resolve on the morph class.
|   4. reason_code enum is validated (422 on invalid).
|   5. body max length enforced at 2000 chars (422 on overage).
|   6. anonymous POST redirects to login (auth middleware fires first).
*/

use App\Models\AbuseReport;
use App\Models\Clan;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    // Plan 09-11 — clear the report-abuse + auth buckets so tests run from a
    // clean state. The key shape mirrors AppServiceProvider::boot definitions.
    RateLimiter::clear('ip:127.0.0.1');
});

it('creates an abuse_reports row on POST /reports', function (): void {
    $reporter = User::factory()->create();
    $targetPlayer = Player::factory()->create();

    $response = $this->actingAs($reporter)->post(route('reports.store'), [
        'target_type' => Player::class,
        'target_id' => $targetPlayer->id,
        'reason_code' => 'harassment',
        'body' => 'Spamming racial slurs in match chat.',
    ]);

    $response->assertRedirect();

    expect(AbuseReport::query()->count())->toBe(1);

    $row = AbuseReport::query()->firstOrFail();

    expect($row->reporter_user_id)->toBe($reporter->id)
        ->and($row->target_type)->toBe(Player::class)
        ->and($row->target_id)->toBe((string) $targetPlayer->id)
        ->and($row->reason_code)->toBe('harassment')
        ->and($row->status)->toBe('pending')
        ->and($row->body)->toBe('Spamming racial slurs in match chat.');
});

it('writes an activity_log row with causer=reporter, subject=target, log=abuse.reported', function (): void {
    $reporter = User::factory()->create();
    $targetClan = Clan::factory()->create();

    $this->actingAs($reporter)->post(route('reports.store'), [
        'target_type' => Clan::class,
        'target_id' => $targetClan->id,
        'reason_code' => 'spam',
        'body' => 'Brigading new players.',
    ])->assertRedirect();

    $activity = Activity::query()->where('description', 'abuse.reported')->firstOrFail();

    expect($activity->causer_id)->toBe($reporter->id)
        ->and($activity->causer_type)->toBe(User::class)
        ->and($activity->subject_id)->toBe($targetClan->id)
        ->and($activity->subject_type)->toBe(Clan::class)
        ->and($activity->properties->get('reason_code'))->toBe('spam')
        ->and($activity->properties->get('target_type'))->toBe(Clan::class);
});

it('returns 404 when target_id does not resolve on the morph class', function (): void {
    $reporter = User::factory()->create();
    $bogusUuid = '00000000-0000-0000-0000-000000000000';

    $this->actingAs($reporter)->post(route('reports.store'), [
        'target_type' => Player::class,
        'target_id' => $bogusUuid,
        'reason_code' => 'cheating',
        'body' => 'Definitely a wallhack.',
    ])->assertStatus(404);

    expect(AbuseReport::query()->count())->toBe(0);
});

it('returns 422 when reason_code is not in the allowed enum', function (): void {
    $reporter = User::factory()->create();
    $targetPlayer = Player::factory()->create();

    $this->actingAs($reporter)
        ->from('/reports/create')
        ->post(route('reports.store'), [
            'target_type' => Player::class,
            'target_id' => $targetPlayer->id,
            'reason_code' => 'definitely_not_a_valid_code',
            'body' => 'Some body text.',
        ])
        ->assertStatus(302)
        ->assertSessionHasErrors('reason_code');

    expect(AbuseReport::query()->count())->toBe(0);
});

it('returns 422 when body exceeds the 2000-char cap', function (): void {
    $reporter = User::factory()->create();
    $targetPlayer = Player::factory()->create();
    $longBody = str_repeat('A', 2001);

    $this->actingAs($reporter)
        ->from('/reports/create')
        ->post(route('reports.store'), [
            'target_type' => Player::class,
            'target_id' => $targetPlayer->id,
            'reason_code' => 'other',
            'body' => $longBody,
        ])
        ->assertStatus(302)
        ->assertSessionHasErrors('body');

    expect(AbuseReport::query()->count())->toBe(0);
});

it('redirects anonymous visitors to the login flow on POST /reports', function (): void {
    $targetPlayer = Player::factory()->create();

    $this->post(route('reports.store'), [
        'target_type' => Player::class,
        'target_id' => $targetPlayer->id,
        'reason_code' => 'harassment',
        'body' => 'Anon body.',
    ])->assertStatus(302); // Auth middleware redirects (302) to the auth flow.

    expect(AbuseReport::query()->count())->toBe(0);
});
