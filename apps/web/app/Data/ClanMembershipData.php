<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ClanMembership;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .docs/05-database-schema.md § clan_memberships.
 *
 * `username`, `avatar_url`, and `player_slug` are convenience denormalisations
 * from the related User + Player — populated by the factory method for the
 * Vue MemberRow component.
 *
 * Timestamps are serialised as ISO 8601 strings (Eloquent's default behaviour
 * when casting `datetime` columns to JSON).
 *
 * Use `ClanMembershipData::fromModel($membership)` to construct from an Eloquent
 * model — auto-mapping cannot resolve the denormalised User/Player fields.
 */
#[TypeScript]
final class ClanMembershipData extends Data
{
    public function __construct(
        public string $id,
        public string $clan_id,
        public string $user_id,
        public string $role,
        public ?string $joined_at,
        public ?string $left_at,
        public ?string $invited_by,
        public ?string $username,
        public ?string $avatar_url,
        public ?string $player_slug,
    ) {}

    /**
     * Build a ClanMembershipData from an Eloquent ClanMembership model.
     *
     * Requires `user` and `user.player` to be eager-loaded or already on the model.
     */
    public static function fromModel(ClanMembership $membership): self
    {
        $user = $membership->relationLoaded('user') ? $membership->user : null;
        $player = $user?->relationLoaded('player') ? $user->player : null;

        return new self(
            id: $membership->id,
            clan_id: $membership->clan_id,
            user_id: $membership->user_id,
            role: $membership->role,
            joined_at: $membership->joined_at !== null ? (string) $membership->joined_at : null,
            left_at: $membership->left_at !== null ? (string) $membership->left_at : null,
            invited_by: $membership->invited_by,
            username: $user?->username,
            avatar_url: $user?->avatar_url,
            player_slug: $player?->slug,
        );
    }
}
