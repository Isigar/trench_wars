<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples § Migration: games table.
 *
 * Generic Game table (D-007). `key` is a slug-safe identifier like `hll`, `cs2`, `r6s`.
 * `name` is jsonb for spatie/laravel-translatable HasTranslations (Plan 03-03).
 * `is_active` lets admin temporarily hide a game without deleting its data (cascade-safety).
 *
 * Constraints:
 *   - games.key UNIQUE (game-level identifier) — Schema unique() generates `games_key_unique`.
 *   - games_key_format_check: CHECK (key ~ '^[a-z0-9_]+$') — DB-layer slug guard (Pitfall T-03-02-03).
 *   - id default gen_random_uuid() so raw SQL inserts (seeders, future imports) don't need UUID generation client-side.
 *   - created_at/updated_at upgraded to timestamptz with UTC interpretation — Phase 1/2 pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('key')->unique();
            $table->jsonb('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE games ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE games ADD CONSTRAINT games_key_format_check CHECK (key ~ '^[a-z0-9_]+$');");
        DB::statement("ALTER TABLE games ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE games ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
