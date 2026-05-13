<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 1 (match_mvps) +
 *         04-02-PLAN.md <interfaces> match_mvps block.
 *
 * Per-category MVPs of a match result. A player may be MVP in multiple categories of
 * the same result (e.g. kills AND objective), but a (result, category, player) triple
 * is unique — composite UNIQUE `match_mvps_unique` enforces this.
 *
 * Category string defended by `match_mvps_category_check` CHECK constraint
 * IN ('kills','defense','objective','mvp') — T-04-02-03 mitigation; Filament enum
 * (plan 04-09 MvpsRelationManager) is the form-layer guard.
 *
 * FK direction (RESEARCH Pattern 1):
 *   match_result_id  → match_results  cascadeOnDelete   (result delete → mvps delete)
 *   player_id        → players        restrictOnDelete   (preserve historical MVP record;
 *                                                         admin must soft-delete player)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_mvps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('match_result_id');
            $table->uuid('player_id');
            $table->text('category');
            $table->integer('value')->nullable();
            $table->timestamps();

            $table->foreign('match_result_id')->references('id')->on('match_results')->cascadeOnDelete();
            $table->foreign('player_id')->references('id')->on('players')->restrictOnDelete();

            $table->unique(['match_result_id', 'category', 'player_id'], 'match_mvps_unique');
        });

        DB::statement('ALTER TABLE match_mvps ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE match_mvps ADD CONSTRAINT match_mvps_category_check CHECK (category IN ('kills','defense','objective','mvp'));");
        DB::statement("ALTER TABLE match_mvps ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE match_mvps ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('match_mvps');
    }
};
