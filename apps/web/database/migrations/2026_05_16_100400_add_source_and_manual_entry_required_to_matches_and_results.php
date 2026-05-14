<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/08-rcon-automation/08-02-PLAN.md task 2 +
 *         08-RESEARCH.md Gap A1 (Phase 4 migration verified by file inspection
 *         does NOT ship match_results.source despite CONTEXT.md claim).
 *
 * Adds two columns required for the manual-override-wins invariant
 * (REQ-success-end-to-end-scrim) + the RCON-failure manual-entry flag (D-019):
 *
 *   1. match_results.source text DEFAULT 'manual'
 *      - CHECK in ('manual','rcon') — T-08-02-02 defence-in-depth.
 *      - MatchResultService::upsertFromRcon (plan 08-08) refuses to overwrite a
 *        row where source='manual' — the database CHECK is the last line of
 *        defence if the service is bypassed.
 *      - Existing Phase 4 rows are backfilled to 'manual' by the DEFAULT clause
 *        (they were inserted by humans through the admin flow).
 *
 *   2. matches.manual_entry_required boolean DEFAULT false
 *      - Flipped TRUE when worker reports RCON unreachable for a played match
 *        (plan 08-11 RconUnreachableFlagsManualTest) — D-019.
 *      - Partial index on (manual_entry_required) WHERE manual_entry_required = true
 *        because only flagged rows are queried by the admin "needs manual entry"
 *        dashboard widget (plan 08-09).
 *
 * down() reverses cleanly: drop CHECK, drop columns, drop partial index. Phase 4
 * row contents preserved (rows stay; columns drop).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_results', function (Blueprint $table): void {
            $table->text('source')->default('manual')->after('axis_score');
        });

        DB::statement("ALTER TABLE match_results ADD CONSTRAINT match_results_source_check CHECK (source IN ('manual','rcon'));");

        Schema::table('matches', function (Blueprint $table): void {
            $table->boolean('manual_entry_required')->default(false)->after('is_public');
        });

        // Partial index — only flagged rows queried by admin dashboard widget.
        DB::statement('CREATE INDEX matches_manual_entry_required_idx ON matches (manual_entry_required) WHERE manual_entry_required = true;');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS matches_manual_entry_required_idx;');

        Schema::table('matches', function (Blueprint $table): void {
            $table->dropColumn('manual_entry_required');
        });

        DB::statement('ALTER TABLE match_results DROP CONSTRAINT IF EXISTS match_results_source_check;');

        Schema::table('match_results', function (Blueprint $table): void {
            $table->dropColumn('source');
        });
    }
};
