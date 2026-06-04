<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ClanData;
use App\Data\ClanMembershipData;
use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\ClanMembership;
use App\Services\PlayerPrivacyGate;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: .planning/phases/02-clans-tags/02-07-PLAN.md Task 1.
 *
 * Public GET /clans/{clan:slug} — clan detail page. No auth required.
 *
 * Security (T-02-04-05): Member roster is filtered by privacy.show_clan_history.
 * Withheld members are ABSENT from the DTO collection (not nulled). The
 * $hiddenMemberCount prop drives the UI notice copy.
 */
class ClanShowController extends Controller
{
    public function __invoke(Clan $clan, PlayerPrivacyGate $gate): Response
    {
        abort_if($clan->status !== 'active', 404);

        $clan->load([
            'tags',
            'activeMembers',
            'activeMembers.user',
            'activeMembers.user.player',
            'activeMembers.user.player.privacy',
        ]);

        $viewer = auth()->user();

        // Viewer-state props for the Apply-to-join block (T-10-06-02).
        $acceptsApplications = (bool) $clan->accepts_applications;
        $viewerIsActiveMember = $viewer !== null
            && ClanMembership::where('user_id', $viewer->id)->whereNull('left_at')->exists();
        $viewerHasPendingApplication = $viewer !== null
            && ClanApplication::where('clan_id', $clan->id)
                ->where('applicant_user_id', $viewer->id)
                ->where('status', 'pending')
                ->exists();

        /** @var Collection<int, ClanMembership> $activeMembers */
        $activeMembers = $clan->activeMembers;
        $totalCount = $activeMembers->count();

        // T-02-04-05: filter members by their show_clan_history privacy flag.
        // Members with no player or no privacy row are excluded defensively.
        $visibleMembers = $activeMembers->filter(function (ClanMembership $membership) use ($gate, $viewer): bool {
            $player = $membership->user?->player;

            if ($player === null) {
                return false;
            }

            return $gate->allowsSection($player, $viewer, 'show_clan_history');
        });

        $hiddenCount = $totalCount - $visibleMembers->count();

        return Inertia::render('Clans/Show', [
            'clan' => ClanData::fromModel($clan),
            'members' => $visibleMembers->values()->map(fn (ClanMembership $m) => ClanMembershipData::fromModel($m))->all(),
            'hiddenMemberCount' => $hiddenCount,
            'acceptsApplications' => $acceptsApplications,
            'viewerIsActiveMember' => $viewerIsActiveMember,
            'viewerHasPendingApplication' => $viewerHasPendingApplication,
        ]);
    }
}
