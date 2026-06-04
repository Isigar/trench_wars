<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source: 11-01-PLAN.md Task 1 — TOUR-04 Stage-level GameMatchType override.
 *
 * `tournament_stages.game_match_type_id` (uuid, nullable, FK → game_match_types):
 *   - NULL = stage uses the tournament's default_game_match_type_id.
 *   - Set = BracketMatchMaterialiserService uses this override instead (plan 11-04).
 *   - nullOnDelete (T-11-01-01 threat mitigation): deleting a GameMatchType nulls
 *     the stage override — the tournament stage SURVIVES (does NOT cascade-delete).
 *     This is critical: game_match_types are configuration rows; losing one must not
 *     destroy tournament structure.
 *
 * down() drops FK constraint first, then the column (required order for Postgres).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_stages', function (Blueprint $table): void {
            $table->uuid('game_match_type_id')->nullable()->after('settings');
            $table->foreign('game_match_type_id')
                ->references('id')
                ->on('game_match_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_stages', function (Blueprint $table): void {
            $table->dropForeign(['game_match_type_id']);
            $table->dropColumn('game_match_type_id');
        });
    }
};
