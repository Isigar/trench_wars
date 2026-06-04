<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source: 11-01-PLAN.md Task 1 — TOUR-02 Clan Elo schema foundation.
 *
 * `clans.elo_rating` (integer, NOT NULL, default 1500):
 *   - Every existing + new clan starts at the canonical base rating of 1500.
 *   - Prevents null-rating leaks into Phase 11 ELO seeding (T-11-01-03 mitigation).
 *
 * `clans.elo_matches_count` (integer, NOT NULL, default 0):
 *   - Tracks number of rated matches; enables provisional-rating display.
 *   - Incremented by EloRatingService::applyResult() in plan 11-02.
 *
 * down() drops in REVERSE column order (elo_matches_count first, then elo_rating).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table): void {
            $table->integer('elo_rating')->default(1500)->after('status');
            $table->integer('elo_matches_count')->default(0)->after('elo_rating');
        });
    }

    public function down(): void
    {
        Schema::table('clans', function (Blueprint $table): void {
            $table->dropColumn(['elo_matches_count', 'elo_rating']);
        });
    }
};
