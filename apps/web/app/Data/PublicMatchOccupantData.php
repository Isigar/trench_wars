<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ClanMembership;
use App\Models\MatchSlot;
use App\Models\Player;
use App\Models\User;
use App\Services\PlayerPrivacyGate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 7 (PublicMatchOccupantData) +
 *         04-07-PLAN.md <interfaces> PublicMatchOccupantData snippet.
 *
 * THE security-critical DTO for /matches/{id}. Vue's Matches/Show.vue receives a
 * collection of these (one per MatchSlot) and renders verbatim — privacy is NEVER
 * re-derived client-side. The controller (plan 04-10) builds the collection by
 * calling `PublicMatchOccupantData::fromMatchSlot($slot, $viewer, $gate)` per slot.
 *
 * camelCase property names (UI-facing convention — matches Phase 2 PublicPlayerData;
 * different from internal MatchSlotData which uses snake_case Phase 3 idiom).
 *
 * Threat refs:
 *   T-04-07-01 (privacy bypass via raw User/Player fields) — mitigated: the DTO
 *     has NO raw FK fields, only the privacy-stripped output.
 *   D-008 invariant: clanTag is ALWAYS shown when the occupant has an active clan,
 *     even when the privacy gate withholds displayName. Clan tags are public per D-008.
 */
#[TypeScript]
final class PublicMatchOccupantData extends Data
{
    public function __construct(
        public string $slotId,
        public string $gameRoleId,
        public int $slotIndex,
        public ?string $displayName,
        public ?string $playerSlug,
        public ?string $clanTag,
        public ?string $clanSlug,
        public bool $isViewer,
    ) {}

    /**
     * Empty-slot factory — no occupant has signed up for this slot yet.
     *
     * NOTE: Named `forEmptySlot` (not `empty`) to avoid override of Spatie's
     * `Data::empty(array $extra, ...): array` static method. The collision would
     * trigger PHPStan covariance errors and break Spatie's empty-DTO contract.
     */
    public static function forEmptySlot(MatchSlot $slot): self
    {
        return new self(
            slotId: $slot->id,
            gameRoleId: $slot->game_role_id,
            slotIndex: $slot->slot_index,
            displayName: null,
            playerSlug: null,
            clanTag: null,
            clanSlug: null,
            isViewer: false,
        );
    }

    /**
     * Privacy-aware factory — applies PlayerPrivacyGate per occupant per match.
     *
     * Flow:
     *   1. No occupant → empty().
     *   2. Resolve Player from User → if no player record, fall through with
     *      User.username as displayName (defensive — Provision creates Player on
     *      first login, but cover the edge case).
     *   3. Tier check: gate->passesTier — if false, withhold name+slug.
     *   4. Section check: gate->allowsSection('show_match_history') — if false,
     *      withhold name+slug.
     *   5. Clan tag: read user.activeClanMembership->clan->tag (D-008 — always public).
     *   6. isViewer: viewer === occupant.
     */
    public static function fromMatchSlot(MatchSlot $slot, ?User $viewer, PlayerPrivacyGate $gate): self
    {
        if ($slot->occupant_user_id === null) {
            return self::forEmptySlot($slot);
        }

        $occupantUser = User::query()->find($slot->occupant_user_id);
        if ($occupantUser === null) {
            return self::forEmptySlot($slot);
        }

        /** @var Player|null $player */
        $player = Player::query()->where('user_id', $occupantUser->id)->first();

        // Resolve the user's active clan + tag (D-008: clan tag is always public).
        /** @var ClanMembership|null $activeMembership */
        $activeMembership = ClanMembership::query()
            ->where('user_id', $occupantUser->id)
            ->whereNull('left_at')
            ->with('clan')
            ->first();
        $clanTag = $activeMembership?->clan?->tag;
        $clanSlug = $activeMembership?->clan?->slug;

        $isViewer = $viewer !== null && $viewer->id === $occupantUser->id;

        // Privacy gate — withhold name+slug when the viewer can't see them.
        // Tier check: 'private' (or community without viewer, etc.) → withhold.
        // Section check: show_match_history flag — if false → withhold.
        $canSee = $isViewer;
        if (! $canSee && $player !== null) {
            $canSee = $gate->passesTier($player, $viewer)
                && $gate->allowsSection($player, $viewer, 'show_match_history');
        }

        $displayName = null;
        $playerSlug = null;
        if ($canSee) {
            $displayName = ($player !== null ? $player->display_name : null)
                ?? $occupantUser->username;
            $playerSlug = $player?->slug;
        }

        return new self(
            slotId: $slot->id,
            gameRoleId: $slot->game_role_id,
            slotIndex: $slot->slot_index,
            displayName: $displayName,
            playerSlug: $playerSlug,
            clanTag: $clanTag,
            clanSlug: $clanSlug,
            isViewer: $isViewer,
        );
    }
}
