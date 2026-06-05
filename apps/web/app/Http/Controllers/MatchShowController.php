<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PublicMatchData;
use App\Data\PublicMatchOccupantData;
use App\Models\GameMatch;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Models\User;
use App\Services\PlayerPrivacyGate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: 04-10-PLAN.md Task 1 + 04-RESEARCH.md Pattern 7 (controller pseudocode).
 *
 * Public GET /matches/{match} — match detail. No auth required (SC-3 second half).
 *
 * Visibility:
 *   - is_public=true                              → reachable by anyone
 *   - is_public=false + viewer is organiser       → reachable by organiser
 *   - is_public=false + viewer is not organiser   → abort(404) (T-04-10-02)
 *
 * Privacy projection (T-04-10-01):
 *   The role-grouped roleGroups prop applies PlayerPrivacyGate::passesTier +
 *   allowsSection('show_match_history') per occupant. Withheld occupants
 *   render with displayName=null + playerSlug=null but clanTag stays visible
 *   (D-008 — clan tags are always public).
 *
 * Security:
 *   T-04-10-02 (existence non-disclosure): abort(404) — not 403 — for private
 *     matches viewed by non-organisers.
 *   T-04-10-08 (server_address leak): PublicMatchData omits server_address.
 *
 * The signupAllowed prop is a UI hint computed WITHOUT a row-lock (UI gate is
 * best-effort; canonical truth is MatchSignupService::signup at POST time).
 * The viewerSlotId prop tells the Vue layer which slot to render the
 * "Cancel signup" affordance on.
 */
class MatchShowController extends Controller
{
    public function __invoke(GameMatch $match, Request $request, PlayerPrivacyGate $gate): Response
    {
        /** @var User|null $viewer */
        $viewer = $request->user();

        // T-04-10-02: private matches return 404 to non-organisers.
        // Organisers can preview their own private matches.
        if (! $match->is_public && $match->organiser_user_id !== $viewer?->id) {
            abort(404);
        }

        $match->load([
            'gameMatchType',
            'gameMatchType.roleLimits',
            'slots',
            'slots.role',
            'slots.occupantUser.player.privacy',
            'slots.occupantUser.activeClanMembership.clan',
            'accessRules.clanTag',
            'result.mvps.player',
        ]);

        // Role-grouped slot DTO collection (Pattern 7). Slots without a role
        // (defensive — shouldn't happen in practice) are filtered out.
        $roleGroups = $match->slots
            ->filter(fn (MatchSlot $slot) => $slot->role !== null)
            ->groupBy('game_role_id')
            ->map(function ($slots) use ($gate, $viewer): array {
                /** @var MatchSlot $first */
                $first = $slots->first();
                /** @var GameRole $role */
                $role = $first->role;

                return [
                    'gameRoleId' => $role->id,
                    'roleKey' => $role->key,
                    'roleDisplayName' => $role->getTranslations('display_name'),
                    'sortOrder' => $role->sort_order,
                    'slots' => $slots
                        ->map(fn (MatchSlot $slot) => PublicMatchOccupantData::fromMatchSlot($slot, $viewer, $gate))
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy('sortOrder')
            ->values()
            ->all();

        return Inertia::render('Matches/Show', [
            'match' => PublicMatchData::fromModel($match),
            'roleGroups' => $roleGroups,
            'signupAllowed' => $this->computeSignupAllowed($match, $viewer),
            'viewerSlotId' => $this->findViewerSlot($match, $viewer)?->id,
            'canDispute' => $this->computeCanDispute($match, $viewer),
            'hasOpenDispute' => $this->viewerHasOpenDispute($match, $viewer),
        ]);
    }

    /**
     * UI hint for the "Dispute the result" affordance. Mirrors
     * StoreMatchDisputeRequest::authorize (which is the canonical gate at POST
     * time): a played match disputed by its organiser or a slot participant.
     */
    private function computeCanDispute(GameMatch $match, ?User $viewer): bool
    {
        if ($viewer === null || $match->status !== 'played') {
            return false;
        }

        if ($match->organiser_user_id === $viewer->id) {
            return true;
        }

        return MatchSlot::query()
            ->where('match_id', $match->id)
            ->where('occupant_user_id', $viewer->id)
            ->exists();
    }

    /**
     * True when the viewer already has an OPEN dispute on this match — the UI
     * swaps the form for a "dispute under review" note (the partial UNIQUE index
     * would reject a second open dispute anyway).
     */
    private function viewerHasOpenDispute(GameMatch $match, ?User $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }

        return $match->disputes()
            ->where('raised_by_user_id', $viewer->id)
            ->where('status', 'open')
            ->exists();
    }

    /**
     * UI-only readability gate — mirrors MatchSignupService preconditions
     * WITHOUT acquiring a row lock (best-effort; service is authoritative).
     *
     * Returns false when:
     *   - viewer is a guest (auth required)
     *   - match.status !== 'open' (signup window closed)
     *   - viewer already occupies any slot in this match (idempotency)
     *   - access rules exist and viewer's active clan tags don't intersect
     */
    private function computeSignupAllowed(GameMatch $match, ?User $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }

        if ($match->status !== 'open') {
            return false;
        }

        // Idempotency check — viewer already has a slot in this match.
        $existing = MatchSlot::query()
            ->where('match_id', $match->id)
            ->where('occupant_user_id', $viewer->id)
            ->exists();
        if ($existing) {
            return false;
        }

        // Tag-access allowlist (Pattern 5; UI mirror — service is the canonical guard).
        $accessRulesCount = $match->accessRules()->count();
        if ($accessRulesCount === 0) {
            return true;
        }

        $userClan = $viewer->activeClanMembership?->clan;
        if ($userClan === null) {
            return false;
        }

        $userTagIds = $userClan->tags()->pluck('clan_tags.id');
        $allowedTagIds = $match->accessRules()->pluck('clan_tag_id');

        return $userTagIds->intersect($allowedTagIds)->isNotEmpty();
    }

    /**
     * Returns the viewer's own occupied slot in this match (if any) so the
     * Vue layer can offer a "Cancel signup" affordance on the right slot.
     */
    private function findViewerSlot(GameMatch $match, ?User $viewer): ?MatchSlot
    {
        if ($viewer === null) {
            return null;
        }

        return MatchSlot::query()
            ->where('match_id', $match->id)
            ->where('occupant_user_id', $viewer->id)
            ->first();
    }
}
