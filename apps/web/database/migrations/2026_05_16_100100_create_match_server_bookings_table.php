<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/08-rcon-automation/08-02-PLAN.md task 1 +
 *         08-RESEARCH.md § Postgres btree_gist + tstzrange Pattern (Pitfall 7).
 *
 * Per-match server reservation with database-tier overlap prevention.
 * `CREATE EXTENSION IF NOT EXISTS btree_gist` runs INSIDE this migration (Phase 1
 * Pitfall 5 extension-in-migration idiom) — composite EXCLUDE on scalar + range
 * requires btree_gist, the Docker postgres image does NOT enable it by default.
 *
 * EXCLUDE constraint (T-08-02-01 mitigation):
 *   EXCLUDE USING gist (
 *     server_id WITH =,
 *     tstzrange(reserved_from, reserved_to, '[)') WITH &&
 *   ) WHERE (status = 'active')
 *
 * Half-open interval `[)` — back-to-back bookings sharing an endpoint do NOT
 * conflict (e.g. one ends at 18:00, next starts at 18:00, both allowed).
 * Partial `WHERE status='active'` — cancelled/completed bookings free their slot.
 *
 * Column inventory:
 *   - id             uuid pk (default gen_random_uuid())
 *   - match_id       uuid FK matches.id cascadeOnDelete
 *   - server_id      uuid FK match_servers.id restrictOnDelete (preserve booking history
 *                                                               even if server retired —
 *                                                               admin soft-disables via is_active)
 *   - reserved_from  timestamptz NOT NULL — typically scheduled_at - 5m
 *   - reserved_to    timestamptz NOT NULL — typically scheduled_end + 30m
 *   - status         text default 'active' — CHECK in ('active','cancelled','completed')
 *   - timestamps()
 *
 * CHECK constraints:
 *   match_server_bookings_status_check — ('active','cancelled','completed')
 *   match_server_bookings_range_check  — reserved_to > reserved_from (defence-in-depth)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pitfall 7 + Phase 1 Pitfall 5: extensions land in their owning migration,
        // NOT the postgres image. Idempotent so re-running on environments where
        // btree_gist already exists (e.g. shared test DB) is safe.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist;');

        Schema::create('match_server_bookings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('match_id');
            $table->uuid('server_id');
            $table->timestampTz('reserved_from');
            $table->timestampTz('reserved_to');
            $table->text('status')->default('active');
            $table->timestamps();

            $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
            $table->foreign('server_id')->references('id')->on('match_servers')->restrictOnDelete();

            $table->index(['server_id', 'reserved_from'], 'msb_server_window_idx');
            $table->index('match_id', 'msb_match_idx');
        });

        DB::statement('ALTER TABLE match_server_bookings ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE match_server_bookings ADD CONSTRAINT match_server_bookings_status_check CHECK (status IN ('active','cancelled','completed'));");
        DB::statement('ALTER TABLE match_server_bookings ADD CONSTRAINT match_server_bookings_range_check CHECK (reserved_to > reserved_from);');
        DB::statement(
            'ALTER TABLE match_server_bookings ADD CONSTRAINT match_server_bookings_no_overlap '
            . "EXCLUDE USING gist (server_id WITH =, tstzrange(reserved_from, reserved_to, '[)') WITH &&) "
            . "WHERE (status = 'active');"
        );
        DB::statement("ALTER TABLE match_server_bookings ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE match_server_bookings ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        // Drop the table (which drops the EXCLUDE constraint with it). Do NOT drop the
        // btree_gist extension — other tables/indexes added in later phases may consume
        // it; CREATE EXTENSION IF NOT EXISTS on the up() path is idempotent.
        Schema::dropIfExists('match_server_bookings');
    }
};
