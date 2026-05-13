<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 1 (match_slots column spec) +
 *         Pattern 2 (one-slot-per-user-per-match invariant) + 04-02-PLAN.md <interfaces> match_slots block.
 *
 * The partial UNIQUE index `match_slots_one_occupancy_per_user` on
 * (match_id, occupant_user_id) WHERE occupant_user_id IS NOT NULL is the DB-layer
 * defense-in-depth half of MatchSignupService idempotency. The Phase 2 D-009 partial-unique
 * index on clan_memberships (one-active-membership-per-user) is the verbatim template —
 * Schema::unique() does not support WHERE predicates so raw DB::statement is mandatory
 * (Pitfall 1).
 *
 * Composite UNIQUE (match_id, game_role_id, slot_index) — named match_slots_unique_slot
 * to fit Postgres' 63-byte identifier limit — blocks duplicate slot rows (T-04-02-01).
 *
 * FK direction (RESEARCH Pattern 1):
 *   match_id          → matches      cascadeOnDelete  (match delete → slot delete)
 *   game_role_id      → game_roles   restrictOnDelete  (preserve historical role refs;
 *                                                       admin must inactivate role instead)
 *   occupant_user_id  → users        nullOnDelete       (user delete → slot becomes vacant,
 *                                                        match continues)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_slots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('match_id');
            $table->uuid('game_role_id');
            $table->integer('slot_index');
            $table->uuid('occupant_user_id')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
            $table->foreign('game_role_id')->references('id')->on('game_roles')->restrictOnDelete();
            $table->foreign('occupant_user_id')->references('id')->on('users')->nullOnDelete();

            $table->unique(['match_id', 'game_role_id', 'slot_index'], 'match_slots_unique_slot');
            $table->index(['match_id', 'occupant_user_id']);
        });

        DB::statement('ALTER TABLE match_slots ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        // Partial UNIQUE: a user occupies at most one slot per match (D-009 analog for matches).
        // Schema::unique() cannot express WHERE; raw SQL is required (Pitfall 1).
        DB::statement('CREATE UNIQUE INDEX match_slots_one_occupancy_per_user ON match_slots (match_id, occupant_user_id) WHERE occupant_user_id IS NOT NULL;');
        DB::statement("ALTER TABLE match_slots ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE match_slots ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS match_slots_one_occupancy_per_user;');
        Schema::dropIfExists('match_slots');
    }
};
