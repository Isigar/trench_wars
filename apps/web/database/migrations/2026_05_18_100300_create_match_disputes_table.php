<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/09-polish/09-02-PLAN.md task 2 +
 *         09-RESEARCH.md "Moderator Tooling — Match Disputes" + Pitfall 11
 *         (one open dispute per (match, user) — partial unique index) +
 *         09-OPEN-QUESTIONS A9 LOCKED (match_id FK = cascadeOnDelete).
 *
 * Per-user-per-match dispute workflow row. State machine:
 *   open → resolved
 *   open → dismissed
 *   open → withdrawn
 *
 * Resolution enum (only meaningful when status='resolved'):
 *   result_amended | result_voided | no_action | sanction_issued
 *
 * Column inventory:
 *   - id                  bigint pk
 *   - match_id            uuid FK matches.id cascadeOnDelete (A9 LOCKED — when a
 *                         match row is removed the dispute log goes with it)
 *   - raised_by_user_id   uuid FK users.id — disputing player
 *   - body                text — dispute narrative from raiser
 *   - status              text default 'open' — state machine enforced by
 *                         DisputeService (plan 09-07); NOT a DB CHECK to keep
 *                         schema portable (Pest enforcement in 09-07)
 *   - resolution          text nullable — set only on resolved status
 *   - resolution_notes    text nullable — moderator narrative
 *   - resolved_by_user_id uuid FK users.id nullable — resolving moderator
 *   - resolved_at         timestamptz nullable
 *   - timestamps          timestamptz
 *
 * Indexes:
 *   md_status_created_idx (status, created_at) — moderator queue view
 *                                                 "open disputes oldest first".
 *   md_match_idx (match_id) — per-match dispute list on match admin page.
 *
 * Partial unique (Pitfall 11):
 *   one_open_dispute_per_user_per_match — UNIQUE (match_id, raised_by_user_id)
 *     WHERE status='open'. Created via raw SQL because Laravel Blueprint does
 *     not expose partial unique. Prevents a user from spamming a single match
 *     with N open disputes; they must withdraw/resolve before raising another.
 *
 * Threat refs:
 *   T-09-02-04 (Elevation of Privilege via resolution column) — plan 09-07
 *     DisputeService enforces moderator-only resolve transitions + writes
 *     activity_log row for every state change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_disputes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignUuid('raised_by_user_id')->constrained('users');
            $table->text('body');
            $table->string('status')->default('open');
            $table->string('resolution')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignUuid('resolved_by_user_id')->nullable()->constrained('users');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'md_status_created_idx');
            $table->index('match_id', 'md_match_idx');
        });

        // Pitfall 11: partial unique index (Laravel Blueprint cannot express
        // WHERE clause on UNIQUE — raw SQL required).
        DB::statement(
            'CREATE UNIQUE INDEX one_open_dispute_per_user_per_match '
            . "ON match_disputes (match_id, raised_by_user_id) WHERE status = 'open';"
        );

        DB::statement("ALTER TABLE match_disputes ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE match_disputes ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        // Partial index drops with the table in dropIfExists, but be explicit
        // to make the reversal symmetric and self-documenting.
        DB::statement('DROP INDEX IF EXISTS one_open_dispute_per_user_per_match;');
        Schema::dropIfExists('match_disputes');
    }
};
