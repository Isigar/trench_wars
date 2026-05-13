<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md § Code Examples § Migration: tournament_brackets table +
 *         06-02-PLAN.md <interfaces> Migration 4.
 *
 * Bracket node table. Each row is one match position in a stage. Carries:
 *   - participant_a_id / participant_b_id : the two competitors (nullable — TBD slots
 *     when generator runs before seeding has completed for that round).
 *   - winner_participant_id : populated by BracketAdvancementService once the
 *     materialised GameMatch resolves (plan 06-08).
 *   - match_id : the FK to the materialised matches row (Phase 4 GameMatch, table 'matches').
 *     Nullable until BracketMatchMaterialiserService creates the GameMatch (plan 06-06).
 *   - advances_to_bracket_id : winner advances here (single+double-elim).
 *   - loser_advances_to_bracket_id : loser drops here (double-elim drop chain — Pattern 6).
 *
 * Defences:
 *   - tournament_brackets_no_self_advance CHECK blocks a bracket pointing at itself
 *     in EITHER advance pointer (T-06-02-02 / Pitfall 11). NULL is allowed because
 *     NULL != id evaluates to NULL (not FALSE) in Postgres.
 *   - tournament_brackets_match_id_unique PARTIAL UNIQUE INDEX WHERE match_id IS NOT NULL
 *     blocks two brackets sharing one GameMatch (T-06-02-03 / Pitfall 4). Multiple NULLs
 *     are allowed (un-materialised brackets coexist).
 *   - tournament_brackets_stage_position composite UNIQUE prevents duplicate logical
 *     positions within a round.
 *
 * FK direction (cascade matrix):
 *   tournament_stage_id            → tournament_stages       cascadeOnDelete
 *   participant_a_id / b / winner  → tournament_participants nullOnDelete
 *   match_id                       → matches                 nullOnDelete
 *   advances_to_bracket_id / loser → self (tournament_brackets) nullOnDelete
 *
 * Self-FK ordering quirk: Laravel emits the `ADD PRIMARY KEY ("id")` ALTER AFTER the
 * `ADD CONSTRAINT FOREIGN KEY` ALTERs in Schema::create(), so Postgres rejects a
 * self-FK declared inline ("no unique constraint matching given keys for referenced
 * table"). Workaround: declare the two self-FKs in a SEPARATE Schema::table() block
 * AFTER Schema::create() completes; by then the PK is established and the FK adds
 * succeed. The non-self FKs stay inline (they reference other tables already created).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_brackets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tournament_stage_id');
            $table->integer('round_number');
            $table->integer('position');
            $table->uuid('participant_a_id')->nullable();
            $table->uuid('participant_b_id')->nullable();
            $table->uuid('winner_participant_id')->nullable();
            $table->uuid('match_id')->nullable();
            $table->uuid('advances_to_bracket_id')->nullable();
            $table->uuid('loser_advances_to_bracket_id')->nullable();
            $table->timestamps();

            $table->foreign('tournament_stage_id')->references('id')->on('tournament_stages')->cascadeOnDelete();
            $table->foreign('participant_a_id')->references('id')->on('tournament_participants')->nullOnDelete();
            $table->foreign('participant_b_id')->references('id')->on('tournament_participants')->nullOnDelete();
            $table->foreign('winner_participant_id')->references('id')->on('tournament_participants')->nullOnDelete();
            $table->foreign('match_id')->references('id')->on('matches')->nullOnDelete();

            $table->index(['tournament_stage_id', 'round_number', 'position']);
            $table->index('match_id');
        });

        // Self-FKs MUST be added after the PK is established (Laravel emits ADD PRIMARY KEY
        // AFTER the inline FK ADD CONSTRAINTs; Postgres rejects self-FKs without a unique
        // constraint on the referenced column). Use Schema::table() to defer them.
        Schema::table('tournament_brackets', function (Blueprint $table): void {
            $table->foreign('advances_to_bracket_id')->references('id')->on('tournament_brackets')->nullOnDelete();
            $table->foreign('loser_advances_to_bracket_id')->references('id')->on('tournament_brackets')->nullOnDelete();
        });

        DB::statement('ALTER TABLE tournament_brackets ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        // CHECK no-self-advance covers BOTH self-FKs (Pitfall 11 / T-06-02-02).
        // NULL != id evaluates to NULL (not FALSE) in Postgres, so NULL pointers are allowed.
        DB::statement('ALTER TABLE tournament_brackets ADD CONSTRAINT tournament_brackets_no_self_advance CHECK (advances_to_bracket_id != id AND loser_advances_to_bracket_id != id);');
        // Partial UNIQUE: one GameMatch per bracket (Pitfall 4 / T-06-02-03).
        // Schema::unique() cannot express WHERE; raw SQL is required.
        DB::statement('CREATE UNIQUE INDEX tournament_brackets_match_id_unique ON tournament_brackets (match_id) WHERE match_id IS NOT NULL;');
        // UNIQUE composite (stage_id, round_number, position) — block duplicate bracket positions.
        DB::statement('CREATE UNIQUE INDEX tournament_brackets_stage_position ON tournament_brackets (tournament_stage_id, round_number, position);');
        DB::statement("ALTER TABLE tournament_brackets ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE tournament_brackets ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tournament_brackets_stage_position;');
        DB::statement('DROP INDEX IF EXISTS tournament_brackets_match_id_unique;');
        Schema::dropIfExists('tournament_brackets');
    }
};
