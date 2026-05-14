<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/08-rcon-automation/08-02-PLAN.md task 1 +
 *         08-RESEARCH.md § Aggregator Pattern (Pitfall 4 — aggregator runs ONCE on
 *         match_end, NOT per event).
 *
 * Per-player aggregated counters for a single match. Populated by
 * MatchPlayerStatAggregatorService (plan 08-08) which reads the match_events
 * stream and upserts ONE row per (match_id, player_id). The composite UNIQUE
 * keys the upsert — replays / re-aggregations are idempotent (REQ-success).
 *
 * Column inventory:
 *   - id            uuid pk (default gen_random_uuid())
 *   - match_id      uuid FK matches.id cascadeOnDelete (events follow match lifecycle)
 *   - player_id     uuid FK players.id restrictOnDelete (preserve stat history; admin
 *                                                       must soft-delete player first)
 *   - kills         integer default 0 — CHECK >= 0
 *   - deaths        integer default 0 — CHECK >= 0
 *   - team_kills    integer default 0 — CHECK >= 0
 *   - score         integer default 0 — CHECK >= 0 (CRCON-reported in-game score)
 *   - role_played   text NULL — canonical game_roles.slug if attributable
 *   - weapons_used  jsonb NULL — { "kar98k": 12, "mg42": 4, ... } weapon→count map
 *   - timestamps()
 *
 * Composite UNIQUE:
 *   mps_match_player_unique — (match_id, player_id) — idempotent upsert key
 *
 * CHECK constraint:
 *   match_player_stats_nonneg_check — all four counters >= 0 (T-04-02-04 mirror)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_player_stats', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('match_id');
            $table->uuid('player_id');
            $table->integer('kills')->default(0);
            $table->integer('deaths')->default(0);
            $table->integer('team_kills')->default(0);
            $table->integer('score')->default(0);
            $table->text('role_played')->nullable();
            $table->jsonb('weapons_used')->nullable();
            $table->timestamps();

            $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
            $table->foreign('player_id')->references('id')->on('players')->restrictOnDelete();

            $table->unique(['match_id', 'player_id'], 'mps_match_player_unique');
        });

        DB::statement('ALTER TABLE match_player_stats ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement('ALTER TABLE match_player_stats ADD CONSTRAINT match_player_stats_nonneg_check CHECK (kills >= 0 AND deaths >= 0 AND team_kills >= 0 AND score >= 0);');
        DB::statement("ALTER TABLE match_player_stats ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE match_player_stats ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('match_player_stats');
    }
};
