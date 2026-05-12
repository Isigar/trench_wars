<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyClan;

use App\Data\ClanData;
use App\Data\ClanMembershipData;
use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: 02-09-PLAN.md Task 2 + RESEARCH.md Pattern 7 — My Clan access gate.
 *
 * GET /my-clan — single entry point for the My Clan tab page.
 *
 * Access gate algorithm:
 *  1. No active membership → render "no clan" state (membership=null, clan=null)
 *  2. Active membership with role 'member' or 'recruit' → redirect to public clan page
 *  3. Active membership with role 'leader' or 'officer' → render management page
 *
 * The invites/applications arrays are empty here; plans 02-10 and 02-11 wire
 * those respectively. The Vue page must tolerate empty lists.
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

        return Inertia::render('MyClan/Index', [
            'membership' => ClanMembershipData::fromModel($membership),
            'clan' => ClanData::fromModel($clan),
            'members' => $activeMembers->map(fn (ClanMembership $m) => ClanMembershipData::fromModel($m))->values()->all(),
            'invites' => [],
            'applications' => [],
        ]);
    }
}
