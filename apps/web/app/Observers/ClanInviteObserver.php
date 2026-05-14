<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ClanInvite;
use App\Notifications\ClanInviteReceived;

/**
 * Source: .planning/phases/09-polish/09-04-PLAN.md task 2.
 *
 * Fires `ClanInviteReceived` to the invitee on row creation. ClanInvites are
 * always created in 'pending' status (see clan_invites table default), so
 * the `created()` hook is the single moment the invitee learns of the invite.
 *
 * State transitions on existing invites (accepted / declined / revoked /
 * expired) are NOT re-notified — the invitee is the actor on
 * accepted/declined and the inviter on revoked; expired is a passive
 * timeout. None of those events warrant a notification to the invitee.
 *
 * Registration: via static::observe() in the ClanInvite model's booted()
 * hook (D-04-08-B precedent — MatchObserver / ClanMembershipObserver /
 * MatchResultObserver all use this idiom).
 */
class ClanInviteObserver
{
    public function created(ClanInvite $invite): void
    {
        $invite->loadMissing('invitee');

        $invitee = $invite->invitee;
        if ($invitee === null) {
            return;
        }

        $invitee->notify(new ClanInviteReceived($invite));
    }
}
