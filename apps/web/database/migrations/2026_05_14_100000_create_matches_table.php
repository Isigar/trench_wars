<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 1 (matches column spec) +
 *         04-02-PLAN.md <interfaces> matches block.
 *
 * The root of the Phase 4 schema. Status string is defended by a CHECK constraint
 * (matches_status_check) — MatchStatusService (plan 04-04) is the canonical write path;
 * the CHECK is defense-in-depth against direct/Console writes (T-04-02-02).
 *
 * Timestamp columns:
 *   - scheduled_at: native timestampTz (NOT timestamp + ALTER). Per Pitfall 8/9 we use
 *     `$table->timestampTz(...)` directly for application-managed datetime columns.
 *   - created_at / updated_at: emitted as plain `timestamp` by $table->timestamps() —
 *     ALTERed to timestamptz with UTC interpretation post-create (Phase 1/2/3 idiom).
 *
 * FK direction (RESEARCH Pattern 1):
 *   game_match_type_id → game_match_types  restrictOnDelete  (preserve historical refs)
 *   organiser_user_id  → users             restrictOnDelete  (preserve audit trail)
 *   host_clan_id       → clans             nullOnDelete       (host clan disbands → match
 *                                                              continues without host)
 *
 * Indexes:
 *   - scheduled_at (single)       — calendar page ORDER BY
 *   - (status, scheduled_at)      — admin filter + sort
 *   - is_public                   — public-view filter (T-04-08 calendar query)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('game_match_type_id');
            $table->jsonb('title');
            $table->jsonb('description')->nullable();
            $table->timestampTz('scheduled_at');
            $table->uuid('organiser_user_id');
            $table->uuid('host_clan_id')->nullable();
            $table->text('server_address')->nullable();
            $table->text('status')->default('draft');
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->foreign('game_match_type_id')->references('id')->on('game_match_types')->restrictOnDelete();
            $table->foreign('organiser_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('host_clan_id')->references('id')->on('clans')->nullOnDelete();

            $table->index('scheduled_at');
            $table->index(['status', 'scheduled_at']);
            $table->index('is_public');
        });

        DB::statement('ALTER TABLE matches ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE matches ADD CONSTRAINT matches_status_check CHECK (status IN ('draft','open','locked','played','cancelled'));");
        DB::statement("ALTER TABLE matches ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE matches ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
