<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .docs/05-database-schema.md § users + D-002 (Discord ID canonical identity).
 *
 * Mirrors the User model's column shape (apps/web/app/Models/User.php) for cross-app
 * type sharing per D-020. Primary key is a uuid string. last_login_at and
 * left_community_at are nullable ISO-8601 timestamp strings (DateTime cast happens
 * at the Eloquent layer; DTOs serialize to string for the JSON / TS surface).
 */
#[TypeScript]
final class UserData extends Data
{
    public function __construct(
        public string $id,
        public string $discord_id,
        public string $username,
        public ?string $email,
        public ?string $avatar_url,
        public string $locale,
        public ?string $last_login_at,
        public ?string $left_community_at,
    ) {}
}
