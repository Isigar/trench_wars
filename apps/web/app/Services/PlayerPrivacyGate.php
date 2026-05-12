<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClanMembership;
use App\Models\Player;
use App\Models\User;

/**
 * Source: .planning/phases/02-clans-tags/02-RESEARCH.md Pattern 2 + D-018.
 *
 * Single source of truth for all privacy decisions on player profiles.
 * Stateless — no constructor injection. Laravel's container auto-resolves.
 *
 * Algorithm per RESEARCH.md Pattern 2:
 * 1. Tier check: private→false (controller returns 404); community→auth required;
 *    clan→same-clan required; public→pass.
 * 2. Own-profile bypass: viewer is the player's owning user → always true.
 * 3. Per-section strip: build DTO with ONLY allowed fields.
 */
final class PlayerPrivacyGate
{
    /**
     * Returns true if the viewer may access the player's profile at all.
     * Returns false when controller should abort(404) — private tier, or tier
     * conditions not met. Own-profile viewer always passes (player can see themselves).
     */
    public function passesTier(Player $player, ?User $viewer): bool
    {
        if ($this->isOwnProfile($viewer, $player)) {
            return true;
        }

        $tier = $player->privacy !== null ? $player->privacy->show_to : 'community';

        return match ($tier) {
            'private' => false,
            'community' => $viewer !== null,
            'clan' => $viewer !== null && $this->viewerInSameClan($viewer, $player),
            'public' => true,
            default => false,
        };
    }

    /**
     * Returns true iff both viewer and player have an active ClanMembership
     * (left_at IS NULL) in the SAME clan_id (intersection, not "any clan").
     * T-02-03-02 mitigation: cross-clan viewer must NOT pass the clan tier.
     */
    public function viewerInSameClan(?User $viewer, Player $player): bool
    {
        if ($viewer === null) {
            return false;
        }

        $viewerClanIds = ClanMembership::where('user_id', $viewer->id)
            ->whereNull('left_at')
            ->pluck('clan_id');

        if ($viewerClanIds->isEmpty()) {
            return false;
        }

        $playerClanIds = ClanMembership::where('user_id', $player->user_id)
            ->whereNull('left_at')
            ->pluck('clan_id');

        return $viewerClanIds->intersect($playerClanIds)->isNotEmpty();
    }

    /**
     * Returns true if the viewer may see the given per-section field.
     * Own-profile viewer always passes.
     * Returns false defensively when no PlayerPrivacy row exists.
     *
     * @param  string  $flag  One of: show_real_name, show_discord_tag,
     *                        show_clan_history, show_match_history, show_stats
     *
     * @throws \InvalidArgumentException For unknown flag names.
     */
    public function allowsSection(Player $player, ?User $viewer, string $flag): bool
    {
        if ($this->isOwnProfile($viewer, $player)) {
            return true;
        }

        $privacy = $player->privacy;

        if ($privacy === null) {
            // Defensive: PlayerPrivacy SHOULD exist (Phase-1 ProvisionFirstLogin
            // creates it), but be defensive to avoid null-access errors.
            return false;
        }

        return match ($flag) {
            'show_real_name' => (bool) $privacy->show_real_name,
            'show_discord_tag' => (bool) $privacy->show_discord_tag,
            'show_clan_history' => (bool) $privacy->show_clan_history,
            'show_match_history' => (bool) $privacy->show_match_history,
            'show_stats' => (bool) $privacy->show_stats,
            default => throw new \InvalidArgumentException("Unknown privacy flag: {$flag}"),
        };
    }

    /**
     * Returns true when the viewer is the User who owns this Player record.
     * A null viewer (guest) always returns false.
     */
    public function isOwnProfile(?User $viewer, Player $player): bool
    {
        return $viewer !== null && $viewer->id === $player->user_id;
    }
}
