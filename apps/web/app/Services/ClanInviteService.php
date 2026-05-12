<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Clan;
use App\Models\ClanInvite;
use App\Models\ClanMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Source: 02-10-PLAN.md Task 1 + RESEARCH.md Pattern 6 — ClanInvite state machine.
 *
 * State machine: pending → accepted | declined | revoked | expired
 *
 * All transitions are logged via LogsActivity on the ClanInvite model.
 * The accept() transition is atomic — invite update + membership creation
 * happen in a single DB::transaction (T-02-06-03 mitigation).
 *
 * Stateless — auto-resolved by the Laravel container.
 */
final class ClanInviteService
{
    /**
     * Send an invite from $inviter (Leader/Officer) to $invitee.
     *
     * Pre-conditions:
     *  - $invitee must not have an active ClanMembership anywhere (D-009)
     *  - $invitee must not already have a pending invite for this $clan
     *
     * @throws \DomainException When either pre-condition is violated.
     */
    public function sendInvite(Clan $clan, User $inviter, User $invitee, ?string $message): ClanInvite
    {
        // Check invitee is not already in a clan (D-009).
        $alreadyMember = ClanMembership::where('user_id', $invitee->id)
            ->whereNull('left_at')
            ->exists();

        if ($alreadyMember) {
            throw new \DomainException(__('clans.invites.error.already_in_clan'));
        }

        // Check no duplicate pending invite for this clan.
        $duplicateInvite = ClanInvite::where('clan_id', $clan->id)
            ->where('invited_user_id', $invitee->id)
            ->where('status', 'pending')
            ->exists();

        if ($duplicateInvite) {
            throw new \DomainException(__('clans.invites.error.duplicate_invite'));
        }

        /** @var ClanInvite $invite */
        $invite = DB::transaction(function () use ($clan, $inviter, $invitee, $message): ClanInvite {
            return ClanInvite::create([
                'clan_id' => $clan->id,
                'inviting_user_id' => $inviter->id,
                'invited_user_id' => $invitee->id,
                'status' => 'pending',
                'message' => $message,
            ]);
        });

        return $invite;
    }

    /**
     * Accept a pending invite and atomically create a ClanMembership.
     *
     * Pre-conditions:
     *  - $acceptor->id must equal $invite->invited_user_id (T-02-06-01 mitigation)
     *  - $invite->status must be 'pending' (T-02-06-02 mitigation)
     *  - $acceptor must not have an active ClanMembership (D-009)
     *
     * The transaction ensures the invite update and membership creation are
     * atomic — if the membership insert fails (e.g., D-009 unique index), the
     * invite status remains 'pending' (T-02-06-03 mitigation).
     *
     * @throws AuthorizationException When identity mismatch.
     * @throws \DomainException When invite is not pending or acceptor is already a member.
     */
    public function accept(ClanInvite $invite, User $acceptor): ClanMembership
    {
        if ($acceptor->id !== $invite->invited_user_id) {
            abort(403);
        }

        if ($invite->status !== 'pending') {
            throw new \DomainException(__('clans.invites.error.not_pending'));
        }

        // D-009: invitee must not already be in a clan.
        $alreadyMember = ClanMembership::where('user_id', $acceptor->id)
            ->whereNull('left_at')
            ->exists();

        if ($alreadyMember) {
            throw new \DomainException(__('clans.invites.error.invitee_in_clan'));
        }

        /** @var ClanMembership $membership */
        $membership = DB::transaction(function () use ($invite, $acceptor): ClanMembership {
            $invite->update([
                'status' => 'accepted',
                'decided_at' => now(),
            ]);

            return ClanMembership::create([
                'clan_id' => $invite->clan_id,
                'user_id' => $acceptor->id,
                'role' => 'recruit',
                'joined_at' => now(),
                'left_at' => null,
                'invited_by' => $invite->inviting_user_id,
            ]);
        });

        return $membership;
    }

    /**
     * Decline a pending invite.
     *
     * Pre-conditions:
     *  - $decliner->id must equal $invite->invited_user_id
     *  - $invite->status must be 'pending'
     *
     * @throws \DomainException When invite is not pending.
     */
    public function decline(ClanInvite $invite, User $decliner): void
    {
        if ($decliner->id !== $invite->invited_user_id) {
            abort(403);
        }

        if ($invite->status !== 'pending') {
            throw new \DomainException(__('clans.invites.error.not_pending'));
        }

        $invite->update([
            'status' => 'declined',
            'decided_at' => now(),
        ]);
    }

    /**
     * Revoke a pending invite. Only a Leader or Officer of the inviting clan may revoke.
     *
     * Pre-conditions:
     *  - $revoker must have an active Leader or Officer membership in $invite->clan_id
     *  - $invite->status must be 'pending'
     *
     * @throws \DomainException When invite is not pending or revoker is unauthorized.
     */
    public function revoke(ClanInvite $invite, User $revoker): void
    {
        $revokerMembership = ClanMembership::where('user_id', $revoker->id)
            ->where('clan_id', $invite->clan_id)
            ->whereNull('left_at')
            ->first();

        if ($revokerMembership === null || ! in_array($revokerMembership->role, ['leader', 'officer'], strict: true)) {
            abort(403);
        }

        if ($invite->status !== 'pending') {
            throw new \DomainException(__('clans.invites.error.not_pending'));
        }

        $invite->update([
            'status' => 'revoked',
            'decided_at' => now(),
        ]);
    }
}
