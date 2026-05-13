<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 1 (match_results) +
 *         04-02-PLAN.md <interfaces> match_results block.
 *
 * 1:1 cardinality with matches: `match_id`->unique() creates the
 * `match_results_match_id_unique` index that lets the HasOne relation in
 * Match::result() (plan 04-03) resolve to a single row. Match deletion cascades
 * down — deleting the match deletes its result, which in turn cascades down to
 * match_mvps (plan 04-02 sibling migration).
 *
 * CHECK `match_results_scores_nonneg_check` allows NULL (results may be filed
 * before the score is known) but rejects negative integers (T-04-02-04).
 *
 * FK direction (RESEARCH Pattern 1):
 *   match_id              → matches  cascadeOnDelete    (1:1 cascade)
 *   winner_clan_id        → clans    nullOnDelete       (clan disband → keep result,
 *                                                        winner becomes anonymous/draw)
 *   recorded_by_user_id   → users    restrictOnDelete    (preserve audit trail)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('match_id')->unique();
            $table->uuid('winner_clan_id')->nullable();
            $table->integer('allies_score')->nullable();
            $table->integer('axis_score')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('recorded_by_user_id');
            $table->timestampTz('recorded_at');
            $table->timestamps();

            $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
            $table->foreign('winner_clan_id')->references('id')->on('clans')->nullOnDelete();
            $table->foreign('recorded_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE match_results ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement('ALTER TABLE match_results ADD CONSTRAINT match_results_scores_nonneg_check CHECK ((allies_score IS NULL OR allies_score >= 0) AND (axis_score IS NULL OR axis_score >= 0));');
        DB::statement("ALTER TABLE match_results ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE match_results ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('match_results');
    }
};
