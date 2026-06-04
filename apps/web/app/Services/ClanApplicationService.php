<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AlreadyInClanException;
use App\Exceptions\ClanNotRecruitingException;
use App\Exceptions\DuplicateApplicationException;
use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\ClanMembership;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Source: 02-11-PLAN.md Task 1 + RESEARCH.md Pattern 6 — ClanApplication state machine.
 *
 * State machine: pending → accepted | declined | cancelled
 *
 * All transitions are logged via LogsActivity on the ClanApplication model.
 * The accept() transition is atomic — application update + membership creation
 * happen in a single DB::transaction (T-02-07-03 mitigation).
 *
 * Stateless — auto-resolved by the Laravel container.
 */
final class ClanApplicationService
{
    /**
     * Create a pending application for an eligible applicant.
     *
     * Guards (in order):
     *  0. Clan must be active (BL-02: suspended/disbanded clans reject all new applications).
     *  1. Clan must be accepting applications (CLAN-04 recruiting toggle).
     *  2. Applicant must not have an active ClanMembership (D-009 one-active invariant).
     *  3. No existing pending application to this clan (CLAN-03 duplicate guard).
     *
     * The clan_applications_one_pending_per_clan partial unique index (plan 10-01)
     * is the last-line defence for Guard 3 in concurrent requests (T-10-02-02).
     * A UniqueConstraintViolationException from a concurrent insert is caught and
     * re-thrown as DuplicateApplicationException so it maps to the existing 422
     * path on both web and bot surfaces (BL-01 race-condition defence).
     *
     * @throws ClanNotRecruitingException When the clan is inactive or not accepting applications.
     * @throws AlreadyInClanException When the applicant already holds an active membership.
     * @throws DuplicateApplicationException When a pending application already exists for this clan.
     */
    public function apply(Clan $clan, User $applicant, ?string $message = null): ClanApplication
    {
        // Guard 0 — BL-02: clan must be active (suspended/disbanded clans do not recruit).
        if ($clan->status !== 'active') {
            throw new ClanNotRecruitingException(__('clans.applications.error.clan_not_recruiting'));
        }

        // Guard 1 — CLAN-04: clan must be accepting applications.
        if (! $clan->accepts_applications) {
            throw new ClanNotRecruitingException(__('clans.applications.error.clan_not_recruiting'));
        }

        // Guard 2 — D-009: applicant must not already be in any clan.
        $applicantAlreadyMember = ClanMembership::where('user_id', $applicant->id)
            ->whereNull('left_at')
            ->exists();

        if ($applicantAlreadyMember) {
            throw new AlreadyInClanException(__('clans.applications.error.already_in_clan'));
        }

        // Guard 3 — CLAN-03: no existing pending application to this clan.
        $duplicatePending = ClanApplication::where('clan_id', $clan->id)
            ->where('applicant_user_id', $applicant->id)
            ->where('status', 'pending')
            ->exists();

        if ($duplicatePending) {
            throw new DuplicateApplicationException(__('clans.applications.error.duplicate_application'));
        }

        // BL-01: wrap create() to catch the DB-level unique constraint violation that
        // occurs when two concurrent requests both pass Guard 3 before either commits.
        // UniqueConstraintViolationException extends QueryException — NOT \DomainException —
        // so it would otherwise bubble to a 500. Translate it to the same domain exception.
        try {
            return ClanApplication::create([
                'clan_id' => $clan->id,
                'applicant_user_id' => $applicant->id,
                'status' => 'pending',
                'message' => $message,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateApplicationException(__('clans.applications.error.duplicate_application'));
        }
    }

    /**
     * Accept a pending application and atomically create a ClanMembership.
     *
     * Pre-conditions:
     *  - $acceptor must have an active Leader or Officer membership in $app->clan_id (T-02-07-01 mitigation)
     *  - $app->status must be 'pending' (T-02-07-02 mitigation)
     *  - Applicant must not already have an active ClanMembership (T-02-07-03 mitigation + D-009)
     *
     * The transaction ensures the application update and membership creation are
     * atomic — if the membership insert fails (e.g., D-009 unique index), the
     * application status remains 'pending' (T-02-07-03 last-line defence).
     *
     * @throws \DomainException When any pre-condition is violated.
     */
    public function accept(ClanApplication $app, User $acceptor): ClanMembership
    {
        // T-02-07-01: Verify acceptor is a Leader or Officer of the target clan.
        $acceptorMembership = ClanMembership::where('user_id', $acceptor->id)
            ->where('clan_id', $app->clan_id)
            ->whereNull('left_at')
            ->first();

        if ($acceptorMembership === null || ! in_array($acceptorMembership->role, ['leader', 'officer'], strict: true)) {
            abort(403);
        }

        // T-02-07-02: Application must be pending.
        if ($app->status !== 'pending') {
            throw new \DomainException(__('clans.applications.error.not_pending'));
        }

        // T-02-07-03: Applicant must not already be in a clan.
        $applicantAlreadyMember = ClanMembership::where('user_id', $app->applicant_user_id)
            ->whereNull('left_at')
            ->exists();

        if ($applicantAlreadyMember) {
            throw new \DomainException(__('clans.applications.error.already_in_clan'));
        }

        /** @var ClanMembership $membership */
        $membership = DB::transaction(function () use ($app, $acceptor): ClanMembership {
            $app->update([
                'status' => 'accepted',
                'decided_at' => now(),
                'decided_by' => $acceptor->id,
            ]);

            return ClanMembership::create([
                'clan_id' => $app->clan_id,
                'user_id' => $app->applicant_user_id,
                'role' => 'recruit',
                'joined_at' => now(),
                'left_at' => null,
                'invited_by' => $acceptor->id,
            ]);
        });

        return $membership;
    }

    /**
     * Decline a pending application.
     *
     * Pre-conditions:
     *  - $decliner must have an active Leader or Officer membership in $app->clan_id
     *  - $app->status must be 'pending'
     *
     * @throws \DomainException When any pre-condition is violated.
     */
    public function decline(ClanApplication $app, User $decliner): void
    {
        // Verify decliner is a Leader or Officer of the target clan.
        $declinerMembership = ClanMembership::where('user_id', $decliner->id)
            ->where('clan_id', $app->clan_id)
            ->whereNull('left_at')
            ->first();

        if ($declinerMembership === null || ! in_array($declinerMembership->role, ['leader', 'officer'], strict: true)) {
            abort(403);
        }

        if ($app->status !== 'pending') {
            throw new \DomainException(__('clans.applications.error.not_pending'));
        }

        $app->update([
            'status' => 'declined',
            'decided_at' => now(),
            'decided_by' => $decliner->id,
        ]);
    }

    /**
     * Cancel a pending application (applicant withdraws their own application).
     *
     * Pre-conditions:
     *  - $applicant->id must equal $app->applicant_user_id
     *  - $app->status must be 'pending'
     *
     * @throws \DomainException When any pre-condition is violated.
     */
    public function cancel(ClanApplication $app, User $applicant): void
    {
        if ($applicant->id !== $app->applicant_user_id) {
            abort(403);
        }

        if ($app->status !== 'pending') {
            throw new \DomainException(__('clans.applications.error.not_pending'));
        }

        $app->update([
            'status' => 'cancelled',
            'decided_at' => now(),
        ]);
    }
}
