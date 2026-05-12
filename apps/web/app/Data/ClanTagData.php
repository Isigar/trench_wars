<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ClanTag;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .docs/05-database-schema.md § clan_tags.
 *
 * `label` is a JSONB locale-keyed array via spatie/laravel-translatable.
 * In TS it surfaces as `Record<string, string> | null`.
 *
 * Use `ClanTagData::fromModel($tag)` to build from a ClanTag Eloquent model —
 * auto-mapping returns the active-locale string for `label`, not the full
 * JSONB array that the DTO expects.
 */
#[TypeScript]
final class ClanTagData extends Data
{
    /**
     * @param  array<string, string>|null  $label
     */
    public function __construct(
        public string $id,
        public string $slug,
        public ?array $label,
        public ?string $color,
    ) {}

    /**
     * Build a ClanTagData from a ClanTag Eloquent model.
     *
     * Uses `getTranslations()` to retrieve the full JSONB locale array
     * rather than the active-locale scalar returned by `$tag->label`.
     */
    public static function fromModel(ClanTag $tag): self
    {
        return new self(
            id: $tag->id,
            slug: $tag->slug,
            label: $tag->getTranslations('label') ?: null,
            color: $tag->color,
        );
    }
}
