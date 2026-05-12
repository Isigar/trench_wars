<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyClan;

use App\Http\Controllers\Controller;
use App\Http\Requests\MyClan\UpdateMemberRoleRequest;
use App\Models\ClanMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Source: 02-09-PLAN.md Task 2.
 *
 * Handles two actions on clan members:
 *  - updateRole: PATCH /my-clan/members/{membership}/role
 *  - remove:     DELETE /my-clan/members/{membership}
 *
 * Both actions gate on ClanMembershipPolicy via the FormRequest or $this->authorize().
 * "Remove" sets left_at = now() (D-009 history preservation — no hard delete).
 */
class MyClanMemberController extends Controller
{
    /**
     * Update a member's role.
     *
     * Authorization: UpdateMemberRoleRequest::authorize() → ClanMembershipPolicy::update
     * + Officer-cannot-promote-to-Leader defence.
     */
    public function updateRole(UpdateMemberRoleRequest $request, ClanMembership $membership): RedirectResponse
    {
        $membership->update(['role' => $request->validated('role')]);

        return redirect()->back()->with('success', __('clans.members.role.update.success'));
    }

    /**
     * Soft-remove a member by setting left_at = now().
     *
     * Authorization: ClanMembershipPolicy::remove (Leader/Officer; Leader cannot
     * remove themselves while still Leader — must demote first).
     *
     * D-009: history preserved — no hard delete.
     */
    public function remove(Request $request, ClanMembership $membership): RedirectResponse
    {
        Gate::authorize('remove', $membership);

        $membership->update(['left_at' => now()]);

        return redirect()->back()->with('success', __('clans.members.remove.success'));
    }
}
