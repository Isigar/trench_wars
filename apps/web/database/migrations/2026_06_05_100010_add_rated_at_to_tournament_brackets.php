<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source: 11-01-PLAN.md Task 1 — TOUR-01/TOUR-02 idempotency marker.
 *
 * `tournament_brackets.rated_at` (timestampTz, nullable):
 *   - NULL = Elo rating has NOT been applied for this bracket result.
 *   - Timestamp = Elo rating was applied at this moment.
 *   - BracketAdvancementService (plan 11-02) checks rated_at IS NULL before calling
 *     EloRatingService::applyResult, then sets rated_at = now() inside the same
 *     transaction. Guards against double-counting (T-11-01 idempotency invariant).
 *
 * No index — lookup is by bracket primary key (UUID), not a range scan on rated_at.
 *
 * down() drops the single column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_brackets', function (Blueprint $table): void {
            $table->timestampTz('rated_at')->nullable()->after('winner_participant_id');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_brackets', function (Blueprint $table): void {
            $table->dropColumn('rated_at');
        });
    }
};
