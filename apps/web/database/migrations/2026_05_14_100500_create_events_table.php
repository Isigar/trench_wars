<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 8 (polymorphic events) +
 *         04-02-PLAN.md <interfaces> events block.
 *
 * Polymorphic calendar table — first Phase 4 table with NO foreign keys. `eventable_type`
 * holds the model FQN string (e.g. 'App\\Models\\Match'); Laravel writes this value via
 * morphTo()/morphOne() relations on the model side (plan 04-03 morphOne on Match;
 * Phase 6 will add a second polymorphic owner for Tournament — Pattern 8).
 *
 * Composite UNIQUE `events_one_per_owner` enforces "at most one Event row per owner
 * entity". MatchObserver (plan 04-08) upserts on save and deletes on destroy — the
 * unique index is the defense-in-depth half (T-04-02-07).
 *
 * The `events_morphable_index` is the standard Laravel polymorphic index covering
 * `Event::where('eventable_type', X)->where('eventable_id', Y)` queries. It exists
 * alongside the UNIQUE because the UNIQUE is the constraint-of-record while the
 * named index is the query-planner anchor with the documented name.
 *
 * Additional read indexes for the public calendar page (plan 04-10):
 *   - starts_at single                 — date-range scans
 *   - (is_public, starts_at) compound  — public-feed filter + sort
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('eventable_type');
            $table->uuid('eventable_id');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->jsonb('title');
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->unique(['eventable_type', 'eventable_id'], 'events_one_per_owner');
            $table->index(['eventable_type', 'eventable_id'], 'events_morphable_index');
            $table->index('starts_at');
            $table->index(['is_public', 'starts_at']);
        });

        DB::statement('ALTER TABLE events ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE events ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE events ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
