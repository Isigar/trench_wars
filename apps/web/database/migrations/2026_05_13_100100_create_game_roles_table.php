<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples § Migration: game_roles table.
 *
 * Game-scoped role catalogue (D-007). `key` is unique WITHIN a game, not globally:
 * `(game_id, key)` composite UNIQUE is the authoritative scope.
 *
 * Constraints / patterns:
 *   - game_roles_game_id_key_unique: explicit named composite UNIQUE (Pitfall 1) — avoids
 *     Laravel auto-name truncation collisions and is greppable via psql `\d`.
 *   - game_roles_key_format_check: CHECK (key ~ '^[a-z0-9_]+$') — DB slug guard (T-03-02-03).
 *   - FK game_id → games.id cascadeOnDelete (Pitfall 7) — deleting a game cleans up its roles.
 *     Safe in Phase 3 because no historical record depends on a specific role row; revisit when
 *     Phase 4 wires signed-up slots to roles directly (Assumption A3).
 *   - id default gen_random_uuid() + timestamptz upgrade (Phase 1/2 idiom).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->text('key');
            $table->jsonb('display_name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();

            // CRITICAL: composite UNIQUE on (game_id, key) — named explicitly per Pitfall 1.
            $table->unique(['game_id', 'key'], 'game_roles_game_id_key_unique');
        });

        DB::statement('ALTER TABLE game_roles ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE game_roles ADD CONSTRAINT game_roles_key_format_check CHECK (key ~ '^[a-z0-9_]+$');");
        DB::statement("ALTER TABLE game_roles ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE game_roles ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('game_roles');
    }
};
