<?php

declare(strict_types=1);

namespace App\Data;

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
}
