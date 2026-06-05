<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ClanInvite;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Invitee-facing projection of a pending ClanInvite, consumed by the "You've
 * been invited" section on /my-clan. Unlike ClanInviteData (the Leader/Officer
 * outgoing view), this carries the issuing clan's display fields + the inviter
 * username so the recipient can decide without a second lookup.
 */
#[TypeScript]
final class ReceivedClanInviteData extends Data
{
    public function __construct(
        public string $id,
        public string $clan_name,
        public string $clan_tag,
        public string $clan_slug,
        public ?string $inviter_username,
        public ?string $message,
        public ?string $expires_at,
    ) {}

    public static function fromModel(ClanInvite $invite): self
    {
        // clan_id is a NOT NULL FK and is eager-loaded by the caller; the
        // null-safe access only guards the (impossible) unloaded/orphan case.
        $clan = $invite->clan;

        return new self(
            id: (string) $invite->id,
            clan_name: (string) $clan?->name,
            clan_tag: (string) $clan?->tag,
            clan_slug: (string) $clan?->slug,
            inviter_username: $invite->inviter?->username,
            message: $invite->message,
            // (string) cast matches the sibling ClanInviteData / ClanApplicationData idiom.
            expires_at: $invite->expires_at !== null ? (string) $invite->expires_at : null,
        );
    }
}
