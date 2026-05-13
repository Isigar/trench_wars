<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples §
 * Migration: game_match_type_role_limits table.
 *
 * Capacity matrix: for each (MatchType, Role) pair, how many slots the match type opens for
 * that role. Three-way junction (Game ↔ MatchType ↔ Role) where the Game dimension is implicit
 * via FKs on both child tables — the cross-game invariant (matchType.game_id === role.game_id)
 * cannot be expressed as a cheap DB CHECK because it spans tables. Per Pitfall 10 / Assumption
 * A6, defense-in-depth runs at the model layer (plan 03-03 `saving()` listener) plus Filament
 * Select scoping (plan 03-07). The DB still enforces:
 *
 *   - gmtrl_match_type_role_unique: composite UNIQUE (game_match_type_id, game_role_id) — the
 *     same pair appears at most once per MatchType. Short name fits Postgres' 63-byte identifier
 *     limit; the full auto-generated name would overflow and collide with Laravel auto-name
 *     truncation (Pitfall 1).
 *   - gmtrl_capacity_check: CHECK (capacity >= 0) — DB-layer half of V5 input validation
 *     (T-03-02-02); Filament form `->minValue(0)` is the first half.
 *   - Both FKs cascadeOnDelete (Pitfall 7): RoleLimits are configuration, not historical
 *     records — safe to drop when either parent goes. Revisit in Phase 4 if signed-up slots
 *     reference RoleLimit rows directly (Assumption A3).
 *   - id default gen_random_uuid() + timestamptz upgrade (Phase 1/2 idiom).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_match_type_role_limits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('game_match_type_id');
            $table->uuid('game_role_id');
            $table->integer('capacity');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Both FKs cascadeOnDelete (Pitfall 7); cross-game invariant enforced at model
            // layer (Pitfall 10), not via SQL CHECK (would need a Postgres trigger function).
            $table->foreign('game_match_type_id')->references('id')->on('game_match_types')->cascadeOnDelete();
            $table->foreign('game_role_id')->references('id')->on('game_roles')->cascadeOnDelete();

            // Short name to fit Postgres' 63-byte identifier limit (auto-name would overflow).
            $table->unique(
                ['game_match_type_id', 'game_role_id'],
                'gmtrl_match_type_role_unique'
            );
        });

        DB::statement('ALTER TABLE game_match_type_role_limits ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement('ALTER TABLE game_match_type_role_limits ADD CONSTRAINT gmtrl_capacity_check CHECK (capacity >= 0);');
        DB::statement("ALTER TABLE game_match_type_role_limits ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE game_match_type_role_limits ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('game_match_type_role_limits');
    }
};
