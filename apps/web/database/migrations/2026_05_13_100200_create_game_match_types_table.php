<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples § Migration: game_match_types table.
 *
 * Game-scoped match type catalogue (D-007). `key` examples: `scrim_50v50`, `tournament`,
 * `training`. The (game_id, key) composite UNIQUE is the authoritative scope.
 *
 * `description` is nullable jsonb — some match types ship without prose; Filament Edit page
 * (Pitfall 2) coerces null → ['en' => ''] before save so the HasTranslations accessor stays sane.
 *
 * Constraints / patterns:
 *   - game_match_types_game_id_key_unique: explicit named composite UNIQUE (Pitfall 1).
 *   - game_match_types_key_format_check: CHECK (key ~ '^[a-z0-9_]+$') — DB slug guard.
 *   - FK game_id → games.id cascadeOnDelete (Pitfall 7) — deleting a game drops its match types
 *     (and via the role-limit table cascade, the capacity rows under them).
 *   - id default gen_random_uuid() + timestamptz upgrade (Phase 1/2 idiom).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_match_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->text('key');
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();

            // CRITICAL: composite UNIQUE on (game_id, key) — named explicitly per Pitfall 1.
            $table->unique(['game_id', 'key'], 'game_match_types_game_id_key_unique');
        });

        DB::statement('ALTER TABLE game_match_types ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE game_match_types ADD CONSTRAINT game_match_types_key_format_check CHECK (key ~ '^[a-z0-9_]+$');");
        DB::statement("ALTER TABLE game_match_types ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE game_match_types ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('game_match_types');
    }
};
