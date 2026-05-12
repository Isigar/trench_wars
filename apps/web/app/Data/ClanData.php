<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Clan;
use App\Models\ClanTag;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .docs/05-database-schema.md § clans.
 *
 * `description` is a JSONB locale-keyed array via spatie/laravel-translatable.
 * In TS it surfaces as `Record<string, string> | null`.
 *
 * `tags` is a denormalised list of ClanTagData for use on the clan directory
 * and clan detail pages without an extra round-trip.
 *
 * Use `ClanData::fromModel($clan)` to construct from a Clan Eloquent model —
 * spatie/laravel-data auto-mapping cannot resolve `active_member_count` or
 * the nested `tags` ClanTagData collection from raw Eloquent model properties.
 */
#[TypeScript]
final class ClanData extends Data
{
    /**
     * @param  array<string, string>|null  $description
     * @param  list<ClanTagData>  $tags
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $tag,
        public string $name,
        public ?array $description,
        public ?string $country_code,
        public string $status,
        public ?string $discord_role_id,
        /** @var list<ClanTagData> */
        public array $tags,
        public int $active_member_count,
    ) {}

    /**
     * Build a ClanData from a Clan model.
     *
     * Requires `tags` and `activeMembers` to be eager-loaded or already on the model.
     */
    public static function fromModel(Clan $clan): self
    {
        /** @var list<ClanTagData> $tags */
        $tags = $clan->relationLoaded('tags')
            ? $clan->tags->map(fn (ClanTag $t) => ClanTagData::fromModel($t))->all()
            : [];

        $activeMemberCount = $clan->relationLoaded('activeMembers')
            ? $clan->activeMembers->count()
            : 0;

        return new self(
            id: $clan->id,
            slug: $clan->slug,
            tag: $clan->tag,
            name: $clan->name,
            description: $clan->getTranslations('description') ?: null,
            country_code: $clan->country_code,
            status: $clan->status,
            discord_role_id: $clan->discord_role_id,
            tags: $tags,
            active_member_count: $activeMemberCount,
        );
    }
}
