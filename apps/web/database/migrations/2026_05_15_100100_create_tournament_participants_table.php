<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md +
 *         06-02-PLAN.md <interfaces> Migration 2.
 *
 * Tournament-Clan join with seed/status/placement. UNIQUE(tournament_id, clan_id)
 * blocks a clan from registering twice for the same tournament (T-06-02-04).
 * `status` is CHECK-defended to the 4 lifecycle values (RESEARCH Pitfall 12).
 *
 * FK direction (cascade matrix):
 *   tournament_id → tournaments  cascadeOnDelete  (delete tournament → wipe participants)
 *   clan_id       → clans        restrictOnDelete (preserve participant audit trail; admin
 *                                                  must explicitly withdraw before clan delete)
 *
 * Timestamp columns:
 *   - registered_at: native timestampTz with useCurrent default (application-managed).
 *   - created_at / updated_at: timestamp → timestamptz post-create (Phase 1-4 idiom).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tournament_id');
            $table->uuid('clan_id');
            $table->integer('seed')->nullable();
            $table->text('status')->default('registered');
            $table->integer('placement')->nullable();
            $table->timestampTz('registered_at')->useCurrent();
            $table->timestamps();

            $table->foreign('tournament_id')->references('id')->on('tournaments')->cascadeOnDelete();
            $table->foreign('clan_id')->references('id')->on('clans')->restrictOnDelete();

            $table->index(['tournament_id', 'status']);
        });

        DB::statement('ALTER TABLE tournament_participants ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE tournament_participants ADD CONSTRAINT tournament_participants_status_check CHECK (status IN ('registered','active','withdrawn','disqualified'));");
        // UNIQUE composite (tournament_id, clan_id) — one entry per clan per tournament (D-009 analog).
        // Named explicitly per Pitfall 1 (Postgres 63-byte identifier limit; 30 chars here).
        DB::statement('CREATE UNIQUE INDEX tournament_participants_unique ON tournament_participants (tournament_id, clan_id);');
        DB::statement("ALTER TABLE tournament_participants ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE tournament_participants ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tournament_participants_unique;');
        Schema::dropIfExists('tournament_participants');
    }
};
