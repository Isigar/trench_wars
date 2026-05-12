<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyClan;

use App\Http\Controllers\Controller;
use App\Http\Requests\MyClan\StoreClanInviteRequest;
use App\Models\Clan;
use App\Models\ClanInvite;
use App\Models\ClanMembership;
use App\Models\User;
use App\Services\ClanInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Source: 02-10-PLAN.md Task 1.
 *
 * Handles invite lifecycle actions for the My Clan management surface.
 *
 * Routes (all require auth middleware):
 *   POST   /my-clan/invites                -> store  (Leader/Officer sends invite)
 *   DELETE /my-clan/invites/{invite}       -> destroy (Leader/Officer revokes invite)
 *   POST   /invites/{invite}/accept        -> accept  (Invitee accepts their invite)
 *   POST   /invites/{invite}/decline       -> decline (Invitee declines their invite)
 *
 * The accept/decline routes are NOT under /my-clan prefix because the accepting
 * user may not yet have a clan membership.
 */
class ClanInviteController extends Controller
{
    /**
     * Send an invite to a user.
     *
     * The actor's clan is resolved server-side from their active membership —
     * it is NOT taken from the request body (T-02-06-05 mitigation).
     */
    public function store(StoreClanInviteRequest $request, ClanInviteService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        /** @var ClanMembership $actorMembership */
        $actorMembership = ClanMembership::where('user_id', $actor->id)
            ->whereNull('left_at')
            ->with('clan')
            ->firstOrFail();

        /** @var Clan $clan */
        $clan = $actorMembership->clan;

        /** @var User $invitee */
        $invitee = User::findOrFail($request->validated('invited_user_id'));

        try {
            $service->sendInvite($clan, $actor, $invitee, $request->validated('message'));
        } catch (\DomainException $e) {
            throw ValidationException::withMessages([
                'invited_user_id' => [$e->getMessage()],
            ]);
        }

        return redirect()->back()->with('success', __('clans.invites.sent'));
    }

    /**
     * Revoke a pending invite (Leader/Officer of the issuing clan only).
     *
     * Identity and role check delegated to ClanInviteService::revoke which
     * aborts 403 if the actor is not a Leader/Officer in the invite's clan.
     */
    public function destroy(Request $request, ClanInvite $invite, ClanInviteService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $service->revoke($invite, $actor);
        } catch (\DomainException $e) {
            return redirect()->back()->withErrors(['invite' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', __('clans.invites.revoked'));
    }

    /**
     * Accept a pending invite.
     *
     * The service asserts $actor->id === $invite->invited_user_id (T-02-06-01).
     * On success the actor has a new ClanMembership with role='recruit'.
     */
    public function accept(Request $request, ClanInvite $invite, ClanInviteService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $service->accept($invite, $actor);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages([
                'invite' => [$e->getMessage()],
            ]);
        }

        return redirect()->route('my-clan.index')->with('success', __('clans.invites.accepted'));
    }

    /**
     * Decline a pending invite.
     *
     * The service asserts $actor->id === $invite->invited_user_id.
     */
    public function decline(Request $request, ClanInvite $invite, ClanInviteService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $service->decline($invite, $actor);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages([
                'invite' => [$e->getMessage()],
            ]);
        }

        return redirect()->back()->with('success', __('clans.invites.declined'));
    }
}
