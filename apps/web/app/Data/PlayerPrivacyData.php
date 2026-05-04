<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .docs/05-database-schema.md § player_privacy + D-018 (per-section + global tier).
 *
 * Mirrors the PlayerPrivacy model (apps/web/app/Models/PlayerPrivacy.php). `show_to`
 * is a string column with the literal values 'public' | 'community' | 'clan' | 'private'
 * (constraint enforced at the DB layer in plan 01-10's migration).
 */
#[TypeScript]
final class PlayerPrivacyData extends Data
{
    public function __construct(
        public string $id,
        public string $player_id,
        public string $show_to,
        public bool $show_real_name,
        public bool $show_discord_tag,
        public bool $show_clan_history,
        public bool $show_match_history,
        public bool $show_stats,
    ) {}
}
