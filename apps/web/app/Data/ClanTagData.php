<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .docs/05-database-schema.md § clan_tags.
 *
 * `label` is a JSONB locale-keyed array via spatie/laravel-translatable.
 * In TS it surfaces as `Record<string, string> | null`.
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
}
