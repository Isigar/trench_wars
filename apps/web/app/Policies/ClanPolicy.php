<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\User;

/**
 * Source: 02-09-PLAN.md Task 1 + RESEARCH.md § Security Domain V4 — Access Control.
 *
 * Authorization matrix:
 *  - view:               always true — public clan pages require no auth
 *  - update:             Leader or Officer of the clan (active membership)
 *  - delete:             always false from My Clan — only Filament admin can delete
 *  - transferLeadership: Leader only
 *
 * "Officer cannot promote to Leader" is enforced in the controller/FormRequest
 * (the policy has no access to the desired new role at authorize()-time).
 * This is documented here so the enforcement split is discoverable.
 */
final class ClanPolicy
{
    /**
     * Any user (including guests) may view a clan.
     * The controller decides whether to render 404 based on status.
     */
    public function view(?User $actor, Clan $clan): bool
    {
        return true;
    }

    /**
     * Only an active Leader or Officer may update the clan profile.
     *
     * This gate guards the My Clan profile-edit form. It intentionally does NOT
     * check discord_role_id — that field is excluded from UpdateClanProfileRequest
     * (T-02-05-02 mass-assignment mitigation).
     */
    public function update(User $actor, Clan $clan): bool
    {
        return $this->actorMembershipInClan($actor, $clan, ['leader', 'officer']) !== null;
    }

    /**
     * Hard-deny delete from My Clan surfaces. Filament admin uses its own gate.
     */
    public function delete(User $actor, Clan $clan): bool
    {
        return false;
    }

    /**
     * Only the current Leader may initiate a leadership transfer.
     * Transferring means the Leader demotes themselves; the counterpart
     * must separately be promoted (or accept the role). This gate covers
     * the first half of that flow.
     */
    public function transferLeadership(User $actor, Clan $clan): bool
    {
        return $this->actorMembershipInClan($actor, $clan, ['leader']) !== null;
    }

    /**
     * Return the actor's active membership in the given clan if their role
     * is in the $allowedRoles list, or null if the actor is not a member
     * with an allowed role.
     *
     * @param  list<string>  $allowedRoles
     */
    private function actorMembershipInClan(User $actor, Clan $clan, array $allowedRoles): ?ClanMembership
    {
        /** @var ClanMembership|null $membership */
        $membership = ClanMembership::where('user_id', $actor->id)
            ->where('clan_id', $clan->id)
            ->whereNull('left_at')
            ->first();

        if ($membership === null) {
            return null;
        }

        return in_array($membership->role, $allowedRoles, strict: true) ? $membership : null;
    }
}
