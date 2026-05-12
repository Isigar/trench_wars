<?php

declare(strict_types=1);

namespace App\Data;

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
}
