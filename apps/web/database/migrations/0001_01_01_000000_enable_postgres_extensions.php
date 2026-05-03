<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Source: 01-RESEARCH.md Common Pitfall 5 + .docs/05-database-schema.md.
 *
 * Postgres extensions are per-database, NOT per-cluster. The postgres:16-alpine
 * image creates a database but does NOT auto-enable extensions. Every later
 * migration that uses `gen_random_uuid()` (uuid PKs) or `citext` columns
 * depends on this migration running FIRST — hence the 0001_01_01 prefix that
 * sorts before Laravel's default 0001_01_01_000000_create_users_table.php.
 *
 * Note: Laravel 12 ships its own users/cache/jobs migrations dated 0001_01_01_000000.
 * We name this with the same date but the file ordering by name puts
 * "enable_postgres_extensions" alphabetically before "create_users_table". To be
 * safe we also delete the default users-table migration in this plan (we'll
 * author our own UUID-PK users migration in plan 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto";');
        DB::statement('CREATE EXTENSION IF NOT EXISTS citext;');
    }

    public function down(): void
    {
        // Intentionally not dropping — extensions may be relied on by other migrations.
        // Dropping in reverse-migration would cascade-drop columns of citext type.
    }
};
