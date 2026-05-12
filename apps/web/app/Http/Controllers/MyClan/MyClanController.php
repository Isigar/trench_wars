<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyClan;

use App\Data\ClanApplicationData;
use App\Data\ClanData;
use App\Data\ClanInviteData;
use App\Data\ClanMembershipData;
use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\ClanInvite;
use App\Models\ClanMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: 02-09-PLAN.md Task 2 + RESEARCH.md Pattern 7 — My Clan access gate.
 * Finalised in plan 02-11 with applications prop.
 *
 * GET /my-clan — single entry point for the My Clan tab page.
 *
 * Access gate algorithm:
 *  1. No active membership → render "no clan" state (membership=null, clan=null)
 *  2. Active membership with role 'member' or 'recruit' → redirect to public clan page
 *  3. Active membership with role 'leader' or 'officer' → render management page
 *
 * Both invites (plan 02-10) and applications (plan 02-11) are fully wired.
 * T-02-07-04 mitigation: applications are scoped via membership->clan->applications().
 */
class MyClanController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var ClanMembership|null $membership */
        $membership = ClanMembership::where('user_id', $user->id)
            ->whereNull('left_at')
            ->with(['clan', 'clan.tags', 'clan.activeMembers', 'clan.activeMembers.user'])
            ->first();

        if ($membership === null) {
            return Inertia::render('MyClan/Index', [
                'membership' => null,
                'clan' => null,
                'members' => [],
                'invites' => [],
                'applications' => [],
            ]);
        }

        // Members and recruits are redirected to the public clan page.
        /** @var Clan $clan */
        $clan = $membership->clan;

        if (! in_array($membership->role, ['leader', 'officer'], strict: true)) {
            return redirect()->route('clans.show', $clan->slug);
        }

        // Leader / Officer: render the management page.
        /** @var Collection<int, ClanMembership> $activeMembers */
        $activeMembers = $clan->activeMembers;

        // Pending outgoing invites — eager-load invitee for display.
        /** @var Collection<int, ClanInvite> $pendingInvites */
        $pendingInvites = $clan->invites()
            ->where('status', 'pending')
            ->with(['invitee'])
            ->get();

        // Pending incoming applications — eager-load applicant and their player (T-02-07-04 scoped via clan relation).
        /** @var Collection<int, ClanApplication> $pendingApplications */
        $pendingApplications = $clan->applications()
            ->where('status', 'pending')
            ->with(['applicant.player'])
            ->get();

        return Inertia::render('MyClan/Index', [
            'membership' => ClanMembershipData::fromModel($membership),
            'clan' => ClanData::fromModel($clan),
            'members' => $activeMembers->map(fn (ClanMembership $m) => ClanMembershipData::fromModel($m))->values()->all(),
            'invites' => ClanInviteData::collect($pendingInvites),
            'applications' => ClanApplicationData::collect($pendingApplications),
        ]);
    }
}
