<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ClanMembership;
use App\Models\User;

/**
 * Source: 02-09-PLAN.md Task 1 + RESEARCH.md § Security Domain V4 — Access Control.
 *
 * Authorization matrix:
 *  - update (role change): Leader or Officer in same clan. Officer cannot promote
 *    to Leader (that check happens in UpdateMemberRoleRequest::authorize() because
 *    the desired new role is not available to the policy at authorize()-time).
 *  - remove:               Leader or Officer in same clan. Leader cannot remove
 *    themselves while still holding the leader role (must demote first — D-009).
 *
 * Cross-clan safety (T-02-05-01): the actor's membership clan_id is compared to
 * the target membership's clan_id so a malicious actor from a different clan
 * cannot mutate another clan's members.
 */
final class ClanMembershipPolicy
{
    /**
     * Authorise a role change on $target.
     *
     * Returns true iff:
     *  - actor has an active membership in the same clan as $target
     *  - actor's role is 'leader' or 'officer'
     *
     * NOTE: Officer → Leader promotion is additionally blocked in
     * UpdateMemberRoleRequest::authorize(). This separation is intentional —
     * the policy does not have access to the incoming $request->validated('role')
     * at the time authorize() is called on the FormRequest, so the extra check
     * lives one layer up (defence-in-depth).
     */
    public function update(User $actor, ClanMembership $target): bool
    {
        $actorMembership = $this->actorActiveMembershipInClan($actor, $target->clan_id);

        if ($actorMembership === null) {
            return false;
        }

        return in_array($actorMembership->role, ['leader', 'officer'], strict: true);
    }

    /**
     * Authorise removing (soft-departing) a member.
     *
     * Returns true iff:
     *  - actor has an active membership in the same clan as $target
     *  - actor's role is 'leader' or 'officer'
     *  - actor is NOT trying to remove themselves while they are still the Leader
     *    (Leader must demote to officer/member first — prevents leaving the clan
     *    leaderless via self-removal)
     */
    public function remove(User $actor, ClanMembership $target): bool
    {
        $actorMembership = $this->actorActiveMembershipInClan($actor, $target->clan_id);

        if ($actorMembership === null) {
            return false;
        }

        if (! in_array($actorMembership->role, ['leader', 'officer'], strict: true)) {
            return false;
        }

        // Leader cannot remove themselves without first handing off leadership.
        if ($actorMembership->user_id === $target->user_id && $actorMembership->role === 'leader') {
            return false;
        }

        return true;
    }

    /**
     * Return the actor's active ClanMembership row for the given clan, or null.
     */
    private function actorActiveMembershipInClan(User $actor, string $clanId): ?ClanMembership
    {
        /** @var ClanMembership|null $membership */
        $membership = ClanMembership::where('user_id', $actor->id)
            ->where('clan_id', $clanId)
            ->whereNull('left_at')
            ->first();

        return $membership;
    }
}
