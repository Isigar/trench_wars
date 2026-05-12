<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ClanMembership;
use App\Models\Player;
use App\Models\User;
use App\Services\PlayerPrivacyGate;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/02-clans-tags/02-RESEARCH.md Pattern 2 + D-018.
 *
 * Privacy-shaped player profile DTO for /players/{slug}. Built by
 * PublicPlayerData::fromPlayer() AFTER the controller applies the
 * PlayerPrivacyGate tier check.
 *
 * SECURITY (T-02-03-01): Withheld sections are ABSENT from toArray() output,
 * NOT serialised as null. This prevents client-side enumeration of which fields
 * exist but are hidden ("absent ≠ null" rule from RESEARCH.md Security Domain).
 *
 * Implementation: withheld constructor arguments receive `Optional::create()`
 * instead of their real value. spatie/laravel-data's VisibleDataFieldsResolver
 * strips Optional instances from the transformation output.
 *
 * Union types (e.g. `Optional|string|null`) are required because PHP's type
 * system otherwise rejects storing an Optional in a `?string` property.
 */
#[TypeScript]
final class PublicPlayerData extends Data
{
    /**
     * @param  Optional|array<string, string>|null  $bio
     * @param  Optional|list<array<string, mixed>>|null  $clanHistory
     * @param  Optional|list<mixed>|null  $matchHistory
     * @param  Optional|list<mixed>|null  $stats
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $displayName,
        public string $avatarUrl,
        public bool $isOwnProfile,
        public ?string $countryCode,
        public Optional|string|null $discordTag,
        public Optional|array|null $bio,
        public Optional|ClanMembershipData|null $currentClan,
        public Optional|array|null $clanHistory,
        public Optional|array|null $matchHistory,
        public Optional|array|null $stats,
    ) {}

    /**
     * Privacy-aware static factory.
     *
     * Constructs the DTO by calling PlayerPrivacyGate for each per-section flag.
     * Fields the gate withholds receive `Optional::create()` so they are ABSENT
     * from the serialised JSON (not null).
     *
     * IMPORTANT: The controller MUST call PlayerPrivacyGate::passesTier() before
     * calling this factory and abort(404) when it returns false. This factory
     * assumes the tier check already passed.
     */
    public static function fromPlayer(Player $player, ?User $viewer, PlayerPrivacyGate $gate): self
    {
        $isOwnProfile = $gate->isOwnProfile($viewer, $player);

        // Discord tag — show_discord_tag flag.
        // Phase 1: User.discord_id is the canonical identity; the display tag is
        // derived as "@{username}". Phase 2 note: show_real_name is forward-compat
        // (no real_name column yet); skip gracefully.
        $discordTag = $gate->allowsSection($player, $viewer, 'show_discord_tag')
            ? ($player->user?->username !== null ? '@' . $player->user->username : null)
            : Optional::create();

        // Bio — always present (no per-section flag controls it in D-018).
        $bio = $player->getTranslations('bio') ?: null;

        // Current active clan membership.
        $activeMembership = $player->user?->id !== null
            ? ClanMembership::where('user_id', $player->user_id)
                ->whereNull('left_at')
                ->with(['clan', 'clan.tags'])
                ->first()
            : null;

        $currentClan = $activeMembership !== null
            ? ClanMembershipData::fromModel($activeMembership)
            : null;

        // Clan history — show_clan_history flag.
        $clanHistory = $gate->allowsSection($player, $viewer, 'show_clan_history')
            ? null  // Placeholder: full history list is implemented in plan 02-07.
            : Optional::create();

        // Match history — show_match_history flag (placeholder in P2).
        $matchHistory = $gate->allowsSection($player, $viewer, 'show_match_history')
            ? null  // Placeholder: match history section is a heading in P2.
            : Optional::create();

        // Stats — show_stats flag (placeholder in P2).
        $stats = $gate->allowsSection($player, $viewer, 'show_stats')
            ? null  // Placeholder: stats section is a heading in P2.
            : Optional::create();

        $user = $player->user;
        $displayName = $player->display_name
            ?? ($user !== null ? $user->username : null)
            ?? $player->slug;
        $avatarUrl = $player->avatar_path
            ?? ($user !== null ? $user->avatar_url : null)
            ?? '';

        return new self(
            id: $player->id,
            slug: $player->slug,
            displayName: $displayName,
            avatarUrl: $avatarUrl,
            isOwnProfile: $isOwnProfile,
            countryCode: $player->country_code,
            discordTag: $discordTag,
            bio: $bio,
            currentClan: $currentClan,
            clanHistory: $clanHistory,
            matchHistory: $matchHistory,
            stats: $stats,
        );
    }
}
