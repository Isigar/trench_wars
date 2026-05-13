<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md +
 *         06-02-PLAN.md <interfaces> Migration 3.
 *
 * Stage grouping inside a tournament. The 6 stage types map to the 4 LOCKED formats
 * (D-011): single_elimination → ['elim']; double_elimination → ['winners-bracket',
 * 'losers-bracket','grand-final']; round_robin → ['group']; swiss → ['swiss-round'].
 *
 * UNIQUE(tournament_id, ordinal) blocks two stages at the same display position.
 *
 * FK direction (cascade matrix):
 *   tournament_id → tournaments  cascadeOnDelete  (delete tournament → wipe stages)
 *
 * `settings` JSONB holds per-stage knobs e.g. {grand_final_reset:true} for
 * double-elim grand final stage; format-specific overrides for swiss round count etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_stages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tournament_id');
            $table->text('type');
            $table->integer('ordinal');
            $table->string('name')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();

            $table->foreign('tournament_id')->references('id')->on('tournaments')->cascadeOnDelete();

            $table->index(['tournament_id', 'ordinal']);
        });

        DB::statement('ALTER TABLE tournament_stages ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE tournament_stages ADD CONSTRAINT tournament_stages_type_check CHECK (type IN ('group','elim','swiss-round','winners-bracket','losers-bracket','grand-final'));");
        // UNIQUE composite (tournament_id, ordinal) — prevent two stages at ordinal=1.
        DB::statement('CREATE UNIQUE INDEX tournament_stages_unique_ordinal ON tournament_stages (tournament_id, ordinal);');
        DB::statement("ALTER TABLE tournament_stages ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE tournament_stages ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tournament_stages_unique_ordinal;');
        Schema::dropIfExists('tournament_stages');
    }
};
