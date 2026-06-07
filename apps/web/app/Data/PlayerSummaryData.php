<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Player;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Listing-card projection of a Player for the public /players directory index.
 * Carries only public-by-definition display fields (slug + name + country +
 * avatar). Privacy-tier filtering is applied by the controller via
 * PlayerPrivacyGate::canShowInSearch BEFORE this DTO is built — the same gate
 * the search surface uses (D-018).
 */
#[TypeScript]
final class PlayerSummaryData extends Data
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $displayName,
        public string $avatarUrl,
        public ?string $countryCode,
    ) {}

    public static function fromModel(Player $player): self
    {
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
            displayName: (string) $displayName,
            avatarUrl: (string) $avatarUrl,
            countryCode: $player->country_code,
        );
    }
}
