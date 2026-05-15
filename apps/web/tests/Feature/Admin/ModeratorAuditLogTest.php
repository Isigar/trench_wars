<?php

declare(strict_types=1);

use App\Models\GameMatch;
use App\Models\User;
use App\Services\BanService;
use App\Services\DisputeService;
use Database\Seeders\ModeratorRoleSeeder;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
| Source: .planning/phases/09-polish/09-07-PLAN.md task 1 (Wave 5).
|
| Replaces the Wave 0 RED stub. Locks SC-3 (audit) + D-012 (Filament +
| spatie/activitylog audit infra) invariants for the moderator surface:
|
|   1. Every BanService + DisputeService mutation writes an activity_log row
|      with the correct description, causer, and subject (T-09-07-03 — non-
|      repudiation).
|   2. ModeratorRoleSeeder seeds the moderator role with all 5 permissions
|      (matrix lock — plan 09-07 must_haves).
|   3. Non-moderator users do NOT pick up moderate-* permissions through any
|      seeder side-effect.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(ModeratorRoleSeeder::class);

    $this->moderator = User::factory()->create();
    $this->moderator->givePermissionTo('admin-access');
    $this->moderator->assignRole('moderator');
});

// ---------------------------------------------------------------------------
// activity_log on every moderator action
// ---------------------------------------------------------------------------

it('writes activity_log on ban issued, ban lifted, dispute opened, dispute transitioned', function (): void {
    $target = User::factory()->create();
    $match = GameMatch::factory()->create();

    $bans = app(BanService::class);
    $disputes = app(DisputeService::class);

    // 1. ban issued
    $ban = $bans->issue(
        user: $target,
        reason: 'Audit chain ban — sufficient length.',
        banType: 'temporary',
        expiresAt: now()->addDays(7),
        issuedBy: $this->moderator,
    );

    // 2. ban lifted
    $bans->lift($ban, $this->moderator, 'Audit chain lift reason.');

    // 3. dispute opened
    $dispute = $disputes->open($match, $this->moderator, 'Audit chain dispute body.');

    // 4. dispute transitioned (open -> under_review)
    $disputes->transition($dispute, 'under_review', null, 'Initial review.', $this->moderator);

    // Each operation must have emitted exactly one row of its kind.
    foreach (
        [
            'user.banned' => ['causer' => $this->moderator->id, 'subject' => $target->id, 'subjectType' => User::class],
            'user.ban_lifted' => ['causer' => $this->moderator->id, 'subject' => $target->id, 'subjectType' => User::class],
            'match.dispute_opened' => ['causer' => $this->moderator->id, 'subject' => $match->id, 'subjectType' => GameMatch::class],
            // Subject of dispute_transitioned is the owning GameMatch (UUID PK).
            // See DisputeService::transition docblock for the schema-alignment
            // rationale (D-09-07-A — activity_log.subject_id is uuid; MatchDispute
            // PK is bigint). dispute_id lives in properties for cross-reference.
            'match.dispute_transitioned' => ['causer' => $this->moderator->id, 'subject' => $match->id, 'subjectType' => GameMatch::class],
        ] as $description => $expected
    ) {
        $row = Activity::query()->where('description', $description)->latest('id')->first();
        expect($row)->not->toBeNull("missing activity row for {$description}");
        expect($row->causer_id)->toBe($expected['causer'])
            ->and($row->subject_id)->toBe($expected['subject'])
            ->and($row->subject_type)->toBe($expected['subjectType']);
    }
});

it('records dispute transition from/to properties on every move', function (): void {
    $match = GameMatch::factory()->create();
    $disputes = app(DisputeService::class);

    $dispute = $disputes->open($match, $this->moderator, 'Transition properties test.');
    $disputes->transition($dispute, 'under_review', null, 'Moving to review.', $this->moderator);
    $disputes->transition($dispute->fresh(), 'resolved', 'no_action', 'Closed: no action.', $this->moderator);

    $transitions = Activity::query()
        ->where('description', 'match.dispute_transitioned')
        ->orderBy('id')
        ->get();

    expect($transitions)->toHaveCount(2);

    /** @var array<string, mixed> $first */
    $first = is_array($transitions[0]->properties) ? $transitions[0]->properties : $transitions[0]->properties->toArray();
    expect($first['from'])->toBe('open')
        ->and($first['to'])->toBe('under_review');

    /** @var array<string, mixed> $second */
    $second = is_array($transitions[1]->properties) ? $transitions[1]->properties : $transitions[1]->properties->toArray();
    expect($second['from'])->toBe('under_review')
        ->and($second['to'])->toBe('resolved')
        ->and($second['resolution'])->toBe('no_action');
});

// ---------------------------------------------------------------------------
// ModeratorRoleSeeder + permission matrix
// ---------------------------------------------------------------------------

it('attaches the moderator role to a user via spatie permission seeder', function (): void {
    $user = User::factory()->create();
    $user->assignRole('moderator');

    expect($user->hasRole('moderator'))->toBeTrue()
        ->and($user->hasPermissionTo('moderate-users'))->toBeTrue()
        ->and($user->hasPermissionTo('moderate-disputes'))->toBeTrue()
        ->and($user->hasPermissionTo('moderate-content'))->toBeTrue()
        ->and($user->hasPermissionTo('view-reports'))->toBeTrue()
        ->and($user->hasPermissionTo('manage-reports'))->toBeTrue();
});

it('non-moderator user lacks moderate-users permission', function (): void {
    $user = User::factory()->create();

    expect($user->hasRole('moderator'))->toBeFalse()
        ->and($user->hasPermissionTo('moderate-users'))->toBeFalse()
        ->and($user->hasPermissionTo('moderate-disputes'))->toBeFalse();
});

it('ModeratorRoleSeeder is idempotent across re-runs', function (): void {
    // Second + third invocations must not throw and must not duplicate perms/roles.
    $this->seed(ModeratorRoleSeeder::class);
    $this->seed(ModeratorRoleSeeder::class);

    expect(Role::query()->where('name', 'moderator')->count())->toBe(1)
        ->and(Permission::query()->whereIn('name', ModeratorRoleSeeder::MODERATOR_PERMISSIONS)->count())->toBe(5);
});
