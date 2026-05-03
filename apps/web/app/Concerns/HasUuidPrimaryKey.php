<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

/**
 * Source: .docs/05-database-schema.md — "UUIDv7 primary keys (uuid column type with default
 * gen_random_uuid() for now; switch to UUIDv7 when Postgres 17 ships)."
 *
 * Laravel 11+ HasUuids defaults to UUIDv7 (Str::orderedUuid()). The schema doc explicitly
 * pegs us to v4 from gen_random_uuid() for now, so we override newUniqueId() to keep parity.
 */
trait HasUuidPrimaryKey
{
    use HasUuids;

    public function newUniqueId(): string
    {
        return Str::uuid()->toString(); // v4 — matches Postgres pgcrypto gen_random_uuid()
    }

    /**
     * Columns that should receive a UUID on creation if unset.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['id'];
    }
}
