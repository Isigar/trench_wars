<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .docs/05-database-schema.md § clan_invites.
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
        public string $inviting_user_id,
        public string $status,
        public ?string $message,
        public ?string $decided_at,
        public ?string $expires_at,
    ) {}
}
