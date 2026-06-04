<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source: 11-01-PLAN.md Task 1 — TOUR-03 Median Buchholz column.
 *
 * `tournament_standings.median_buchholz` (decimal(8,2), NOT NULL, default 0):
 *   - Matches the existing `points` and `tiebreak_score` column precision (decimal 8,2).
 *   - Default 0 ensures existing Phase-6 standings inserts that omit this column
 *     succeed without modification (T-11-01-02 DoS-prevention mitigation).
 *   - SwissStandingsCalculator (plan 11-03) will write the computed median Buchholz
 *     value (plain Buchholz with the highest + lowest opponent score dropped).
 *
 * down() drops the single column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_standings', function (Blueprint $table): void {
            $table->decimal('median_buchholz', 8, 2)->default(0)->after('tiebreak_score');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_standings', function (Blueprint $table): void {
            $table->dropColumn('median_buchholz');
        });
    }
};
