<?php

declare(strict_types=1);

use App\Filament\Resources\UserResource;
use App\Models\Ban;
use App\Models\User;
use App\Services\BanService;
use Database\Seeders\ModeratorRoleSeeder;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| Source: .planning/phases/09-polish/09-07-PLAN.md task 1 (Wave 5).
|
| Replaces the Wave 0 RED stub. Covers SC-3 invariants for the ban surface:
|
|   1. BanService::issue() writes a Ban row with the correct shape.
|   2. activity_log row is written with causer=issuer, subject=user
|      (T-09-07-03 — non-repudiation).
|   3. ban_type='temporary' WITHOUT expires_at throws InvalidArgumentException
|      (T-09-07-04 — service-layer correctness gate).
|   4. ban_type='permanent' forces expires_at=null even if caller passes one
|      (defence in depth — active() scope would silently expire it otherwise).
|   5. Filament UserResource exposes the ban + unban BulkActions wired to
|      BanService::issue + ::lift (T-09-07-01 elevation-gate proof; the
|      visibility gate is locked separately in ModeratorPermissionGateTest).
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(ModeratorRoleSeeder::class);

    $this->moderator = User::factory()->create();
    $this->moderator->givePermissionTo('admin-access');
    $this->moderator->assignRole('moderator');

    $this->actingAs($this->moderator);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ---------------------------------------------------------------------------
// Service-level: ::issue() shape + activity_log
// ---------------------------------------------------------------------------

it('issues a temporary ban via service with the correct row shape', function (): void {
    $target = User::factory()->create();
    $service = app(BanService::class);

    $expires = now()->addDays(7);
    $ban = $service->issue(
        user: $target,
        reason: 'Test ban reason — sufficient length.',
        banType: 'temporary',
        expiresAt: $expires,
        issuedBy: $this->moderator,
    );

    expect($ban)->toBeInstanceOf(Ban::class)
        ->and($ban->user_id)->toBe($target->id)
        ->and($ban->ban_type)->toBe('temporary')
        ->and($ban->reason)->toBe('Test ban reason — sufficient length.')
        ->and($ban->expires_at)->not->toBeNull()
        ->and($ban->issued_by_user_id)->toBe($this->moderator->id)
        ->and($ban->lifted_at)->toBeNull();
});

it('writes an activity_log row with causer=issuer and subject=user on issue', function (): void {
    $target = User::factory()->create();
    $service = app(BanService::class);

    $service->issue(
        user: $target,
        reason: 'Audit log assertion.',
        banType: 'temporary',
        expiresAt: now()->addDay(),
        issuedBy: $this->moderator,
    );

    $activity = Activity::query()->where('description', 'user.banned')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->moderator->id)
        ->and($activity->causer_type)->toBe(User::class)
        ->and($activity->subject_id)->toBe($target->id)
        ->and($activity->subject_type)->toBe(User::class);

    /** @var array<string, mixed> $props */
    $props = is_array($activity->properties)
        ? $activity->properties
        : $activity->properties->toArray();
    expect($props['ban_type'])->toBe('temporary')
        ->and($props['reason'])->toBe('Audit log assertion.')
        ->and($props['expires_at'])->not->toBeNull();
});

it('throws InvalidArgumentException when ban_type=temporary has no expires_at', function (): void {
    $target = User::factory()->create();
    $service = app(BanService::class);

    expect(fn () => $service->issue(
        user: $target,
        reason: 'No expiry test.',
        banType: 'temporary',
        expiresAt: null,
        issuedBy: $this->moderator,
    ))->toThrow(InvalidArgumentException::class);
});

it('forces expires_at=null when ban_type=permanent', function (): void {
    $target = User::factory()->create();
    $service = app(BanService::class);

    // Caller passes an expiry — service MUST overwrite it with null because
    // a permanent ban with a non-null expires_at would silently expire.
    $ban = $service->issue(
        user: $target,
        reason: 'Permanent ban — expires_at should be discarded.',
        banType: 'permanent',
        expiresAt: now()->addYear(),
        issuedBy: $this->moderator,
    );

    expect($ban->ban_type)->toBe('permanent')
        ->and($ban->expires_at)->toBeNull();
});

it('rejects unknown ban_type with InvalidArgumentException', function (): void {
    $target = User::factory()->create();
    $service = app(BanService::class);

    expect(fn () => $service->issue(
        user: $target,
        reason: 'Bad ban_type.',
        banType: 'forever-and-ever',
        expiresAt: null,
        issuedBy: $this->moderator,
    ))->toThrow(InvalidArgumentException::class);
});

// ---------------------------------------------------------------------------
// Filament UserResource: BulkAction visibility for moderators + ban/unban wiring
// ---------------------------------------------------------------------------

it('Filament UserResource registers the ban + unban BulkActions for moderators', function (): void {
    $targets = User::factory()->count(2)->create();

    // Acting as moderator — the visibility closure should permit the actions.
    Livewire::test(UserResource\Pages\ListUsers::class)
        ->assertTableBulkActionExists('ban')
        ->assertTableBulkActionExists('unban');
});

it('UserResource ban BulkAction issues bans + writes activity_log via BanService', function (): void {
    $a = User::factory()->create();
    $b = User::factory()->create();

    Livewire::test(UserResource\Pages\ListUsers::class)
        ->callTableBulkAction('ban', [$a->id, $b->id], data: [
            'ban_type' => 'temporary',
            'reason' => 'Bulk ban via Filament — long enough.',
            'expires_at' => now()->addDays(3)->toDateTimeString(),
        ])
        ->assertHasNoTableBulkActionErrors();

    expect(Ban::query()->where('user_id', $a->id)->count())->toBe(1)
        ->and(Ban::query()->where('user_id', $b->id)->count())->toBe(1);

    $logs = Activity::query()->where('description', 'user.banned')->get();
    expect($logs)->toHaveCount(2);
});

it('UserResource unban BulkAction lifts active bans via BanService', function (): void {
    $a = User::factory()->create();
    $b = User::factory()->create();

    $service = app(BanService::class);
    $service->issue($a, 'Initial ban A.', 'temporary', now()->addDays(7), $this->moderator);
    $service->issue($b, 'Initial ban B.', 'temporary', now()->addDays(7), $this->moderator);

    Livewire::test(UserResource\Pages\ListUsers::class)
        ->callTableBulkAction('unban', [$a->id, $b->id], data: [
            'lift_reason' => 'Bulk unban via Filament — reviewed.',
        ])
        ->assertHasNoTableBulkActionErrors();

    expect(Ban::query()->where('user_id', $a->id)->whereNotNull('lifted_at')->count())->toBe(1)
        ->and(Ban::query()->where('user_id', $b->id)->whereNotNull('lifted_at')->count())->toBe(1)
        ->and(Activity::query()->where('description', 'user.ban_lifted')->count())->toBe(2);
});

it('UserResource ban BulkAction enforces required reason field (Pitfall 8)', function (): void {
    $target = User::factory()->create();

    // Empty reason — Filament must render an inline form error rather than
    // silently closing the modal (Pitfall 8 mitigation: ->required() +
    // ->minLength(10) on the BulkAction's `reason` field).
    Livewire::test(UserResource\Pages\ListUsers::class)
        ->callTableBulkAction('ban', [$target->id], data: [
            'ban_type' => 'temporary',
            'reason' => '',
            'expires_at' => now()->addDay()->toDateTimeString(),
        ])
        ->assertHasTableBulkActionErrors(['reason']);

    expect(Ban::query()->where('user_id', $target->id)->count())->toBe(0);
});
