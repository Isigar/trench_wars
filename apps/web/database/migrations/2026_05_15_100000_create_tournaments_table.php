<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md § Code Examples § Migration: tournaments table +
 *         06-02-PLAN.md <interfaces> Migration 1.
 *
 * Root of the Phase 6 schema. `format` and `status` are both defended by CHECK
 * constraints (T-06-02-01 / T-06-02-05 mitigations): the 4 locked formats (D-011)
 * and 6 status values are the LAST line of defence; service-layer validation runs
 * first but a bypass attempt (raw SQL, broken test fixture) is still blocked at the DB.
 *
 * Timestamp columns:
 *   - starts_at / ends_at: native timestampTz (NOT timestamp + ALTER) per Phase 4 Pitfall 8/9.
 *   - created_at / updated_at: emitted as plain `timestamp` by $table->timestamps() —
 *     ALTERed to timestamptz with UTC interpretation post-create (Phase 1/2/3/4 idiom).
 *
 * `title` and `description` are jsonb for spatie/laravel-translatable HasTranslations
 * (D-013); plan 06-03 wires the Tournament model with HasTranslations.
 *
 * FK direction (RESEARCH ## Component Responsibilities + plan 06-02 cascade matrix):
 *   game_id                     → games              restrictOnDelete  (preserve tournament refs)
 *   organiser_user_id           → users              restrictOnDelete  (preserve audit trail)
 *   default_game_match_type_id  → game_match_types   nullOnDelete      (admin reassigns)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->string('slug')->unique();
            $table->jsonb('title');
            $table->jsonb('description')->nullable();
            $table->text('format');
            $table->text('status')->default('draft');
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->integer('max_participants')->nullable();
            $table->jsonb('settings')->nullable();
            $table->uuid('organiser_user_id');
            $table->uuid('default_game_match_type_id')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->foreign('game_id')->references('id')->on('games')->restrictOnDelete();
            $table->foreign('organiser_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('default_game_match_type_id')->references('id')->on('game_match_types')->nullOnDelete();

            $table->index('slug');
            $table->index(['status', 'starts_at']);
            $table->index('game_id');
            $table->index('is_public');
        });

        DB::statement('ALTER TABLE tournaments ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE tournaments ADD CONSTRAINT tournaments_format_check CHECK (format IN ('single_elimination','double_elimination','round_robin','swiss'));");
        DB::statement("ALTER TABLE tournaments ADD CONSTRAINT tournaments_status_check CHECK (status IN ('draft','registering','seeded','running','completed','cancelled'));");
        DB::statement("ALTER TABLE tournaments ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE tournaments ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
