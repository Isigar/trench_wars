<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ClanInvite;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .docs/05-database-schema.md § clan_invites.
 *
 * Outgoing-invite projection for the My Clan "Invites" tab (Leader/Officer view).
 * Carries the invitee username + created_at so the tab shows a human name and the
 * time the invite was sent rather than a raw UUID.
 *
 * Status transitions: pending → accepted | declined | revoked | expired
 * (Pattern 6 in 02-RESEARCH.md).
 */
#[TypeScript]
final class ClanInviteData extends Data
{
    public function __construct(
        public string $id,
        public string $clan_id,
        public string $invited_user_id,
        public ?string $invited_username,
        public string $inviting_user_id,
        public string $status,
        public ?string $message,
        public ?string $created_at,
        public ?string $decided_at,
        public ?string $expires_at,
    ) {}

    public static function fromModel(ClanInvite $invite): self
    {
        return new self(
            id: (string) $invite->id,
            clan_id: (string) $invite->clan_id,
            invited_user_id: (string) $invite->invited_user_id,
            invited_username: $invite->invitee?->username,
            inviting_user_id: (string) $invite->inviting_user_id,
            status: $invite->status,
            message: $invite->message,
            created_at: $invite->created_at?->toIso8601String(),
            decided_at: $invite->decided_at !== null ? (string) $invite->decided_at : null,
            expires_at: $invite->expires_at !== null ? (string) $invite->expires_at : null,
        );
    }
}
