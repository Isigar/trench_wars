<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md +
 *         06-02-PLAN.md <interfaces> Migration 5.
 *
 * Denormalised standings table. One row per (stage, participant) — round-robin
 * tournaments can have multiple stages (groups + playoffs) where the same
 * participant has DIFFERENT standings rows, hence the UNIQUE composite is
 * (tournament_stage_id, participant_id) NOT (tournament_id, participant_id).
 *
 * `points` and `tiebreak_score` use decimal(8,2) for float-safe arithmetic:
 *   - Swiss draws are 0.5 each — integer columns would lose precision.
 *   - 99999.99 max — Swiss with 99 rounds × 99 draws = 49.5, well within bounds.
 * `tiebreak_score` holds Buchholz / SoS / OMV depending on format
 * (StandingsCalculatorService — plan 06-09).
 *
 * `rank` is NULLABLE — computed by StandingsCalculatorService; null until first
 * computation.
 *
 * FK direction (cascade matrix):
 *   tournament_id        → tournaments              cascadeOnDelete
 *   tournament_stage_id  → tournament_stages        cascadeOnDelete
 *   participant_id       → tournament_participants  cascadeOnDelete
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_standings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tournament_id');
            $table->uuid('tournament_stage_id');
            $table->uuid('participant_id');
            $table->integer('wins')->default(0);
            $table->integer('losses')->default(0);
            $table->integer('draws')->default(0);
            $table->decimal('points', 8, 2)->default(0);
            $table->decimal('tiebreak_score', 8, 2)->default(0);
            $table->integer('rank')->nullable();
            $table->timestamps();

            $table->foreign('tournament_id')->references('id')->on('tournaments')->cascadeOnDelete();
            $table->foreign('tournament_stage_id')->references('id')->on('tournament_stages')->cascadeOnDelete();
            $table->foreign('participant_id')->references('id')->on('tournament_participants')->cascadeOnDelete();

            $table->index(['tournament_id', 'rank']);
        });

        DB::statement('ALTER TABLE tournament_standings ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        // UNIQUE composite (stage_id, participant_id) — one standings row per participant per stage.
        DB::statement('CREATE UNIQUE INDEX tournament_standings_unique ON tournament_standings (tournament_stage_id, participant_id);');
        DB::statement("ALTER TABLE tournament_standings ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE tournament_standings ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tournament_standings_unique;');
        Schema::dropIfExists('tournament_standings');
    }
};
