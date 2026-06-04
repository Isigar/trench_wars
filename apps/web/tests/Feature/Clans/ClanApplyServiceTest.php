<?php

declare(strict_types=1);

use App\Exceptions\AlreadyInClanException;
use App\Exceptions\ClanNotRecruitingException;
use App\Exceptions\DuplicateApplicationException;
use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\ClanMembership;
use App\Models\User;
use App\Services\ClanApplicationService;

/*
| Source: 10-02-PLAN.md Task 1 — RED phase.
|
| Covers CLAN-03 + CLAN-04 eligibility guards for ClanApplicationService::apply().
|
| Behaviors:
|   1. Happy path (null message): returns pending ClanApplication, persisted.
|   2. Happy path with message: message stored.
|   3. Guard 1 — accepts_applications=false → ClanNotRecruitingException.
|   4. Guard 2 — applicant has active ClanMembership → AlreadyInClanException; no row created.
|   5. Guard 3 — applicant has pending application to same clan → DuplicateApplicationException; count stays 1.
|   6. Declined-then-reapply edge — prior declined row does NOT block; new pending row created.
*/

it('apply() creates a pending ClanApplication with null message (happy path)', function (): void {
    $clan = Clan::factory()->create(['accepts_applications' => true]);
    $applicant = User::factory()->create();

    $service = app(ClanApplicationService::class);
    $result = $service->apply($clan, $applicant, null);

    expect($result)->toBeInstanceOf(ClanApplication::class);
    expect($result->clan_id)->toBe($clan->id);
    expect($result->applicant_user_id)->toBe($applicant->id);
    expect($result->status)->toBe('pending');
    expect($result->message)->toBeNull();
    expect($result->exists)->toBeTrue();
    expect(ClanApplication::where('clan_id', $clan->id)->where('applicant_user_id', $applicant->id)->where('status', 'pending')->count())->toBe(1);
});

it('apply() stores the optional message', function (): void {
    $clan = Clan::factory()->create(['accepts_applications' => true]);
    $applicant = User::factory()->create();

    $service = app(ClanApplicationService::class);
    $result = $service->apply($clan, $applicant, 'hi');

    expect($result->message)->toBe('hi');
    expect($result->status)->toBe('pending');
});

it('apply() throws ClanNotRecruitingException when accepts_applications is false', function (): void {
    $clan = Clan::factory()->create(['accepts_applications' => false]);
    $applicant = User::factory()->create();

    $service = app(ClanApplicationService::class);

    expect(fn () => $service->apply($clan, $applicant))
        ->toThrow(ClanNotRecruitingException::class);

    expect(ClanApplication::where('applicant_user_id', $applicant->id)->count())->toBe(0);
});

it('apply() throws AlreadyInClanException when applicant has an active membership in any clan', function (): void {
    $clan = Clan::factory()->create(['accepts_applications' => true]);
    $applicant = User::factory()->create();

    // Applicant already has an active membership in another clan.
    $otherClan = Clan::factory()->create();
    ClanMembership::factory()->create([
        'user_id' => $applicant->id,
        'clan_id' => $otherClan->id,
        'left_at' => null,
    ]);

    $service = app(ClanApplicationService::class);

    expect(fn () => $service->apply($clan, $applicant))
        ->toThrow(AlreadyInClanException::class);

    expect(ClanApplication::where('applicant_user_id', $applicant->id)->where('clan_id', $clan->id)->count())->toBe(0);
});

it('apply() throws DuplicateApplicationException when applicant already has a pending application to the same clan', function (): void {
    $clan = Clan::factory()->create(['accepts_applications' => true]);
    $applicant = User::factory()->create();

    // Seed a prior pending application.
    ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    $service = app(ClanApplicationService::class);

    expect(fn () => $service->apply($clan, $applicant))
        ->toThrow(DuplicateApplicationException::class);

    // Pending count stays at 1 — no second row was created.
    expect(
        ClanApplication::where('clan_id', $clan->id)
            ->where('applicant_user_id', $applicant->id)
            ->where('status', 'pending')
            ->count()
    )->toBe(1);
});

it('apply() succeeds when prior application to the same clan was declined (pending-only guard)', function (): void {
    $clan = Clan::factory()->create(['accepts_applications' => true]);
    $applicant = User::factory()->create();

    // Prior declined application — must NOT block re-apply.
    ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'declined',
    ]);

    $service = app(ClanApplicationService::class);
    $result = $service->apply($clan, $applicant, null);

    expect($result->status)->toBe('pending');

    // Exactly 1 pending row exists (the new one); the declined row is untouched.
    expect(
        ClanApplication::where('clan_id', $clan->id)
            ->where('applicant_user_id', $applicant->id)
            ->where('status', 'pending')
            ->count()
    )->toBe(1);
});
