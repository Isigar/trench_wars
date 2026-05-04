<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .docs/05-database-schema.md § players + D-018 (player privacy tier).
 *
 * Mirrors the Player model (apps/web/app/Models/Player.php). The `bio` field is
 * a JSONB column cast to array on the PHP side; in TS it surfaces as
 * `Record<string, string> | null` (locale-keyed translation map per
 * spatie/laravel-translatable conventions used in Phase 2+).
 */
#[TypeScript]
final class PlayerData extends Data
{
    /**
     * @param  array<string, string>|null  $bio
     */
    public function __construct(
        public string $id,
        public string $user_id,
        public string $slug,
        public ?string $display_name,
        public string $avatar_source,
        public ?string $avatar_path,
        public ?array $bio,
        public ?string $country_code,
    ) {}
}
