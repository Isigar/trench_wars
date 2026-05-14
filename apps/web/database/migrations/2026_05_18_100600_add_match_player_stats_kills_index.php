<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/09-polish/09-02-PLAN.md task 2 +
 *         09-RESEARCH.md A5 (top-N leaderboard query needs an index that
 *         supports ordering by kills for a given player or top-N aggregation).
 *
 * Composite index (player_id, kills) on match_player_stats. Postgres B-tree
 * handles DESC ordering on a plain ASC composite index at query time without
 * a separate "DESC" definition — the planner walks the index backwards. We use
 * a plain ASC index first; if seq-scan profiling later proves the leaderboard
 * service (plan 09-05) is doing a Bitmap Heap Scan + Sort, we'll revisit with
 * an explicit `kills DESC` via raw SQL (RESEARCH A5 fallback).
 *
 * Query shape supported (plan 09-05 LeaderboardService::topPlayersByKills):
 *   SELECT player_id, SUM(kills) AS total_kills FROM match_player_stats
 *   GROUP BY player_id ORDER BY total_kills DESC LIMIT 100;
 *
 * Threat refs: none — index is read-only metadata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_player_stats', function (Blueprint $table): void {
            $table->index(['player_id', 'kills'], 'mps_player_kills_idx');
        });
    }

    public function down(): void
    {
        Schema::table('match_player_stats', function (Blueprint $table): void {
            $table->dropIndex('mps_player_kills_idx');
        });
    }
};
