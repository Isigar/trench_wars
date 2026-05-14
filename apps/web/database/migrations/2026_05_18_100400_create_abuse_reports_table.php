<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/09-polish/09-02-PLAN.md task 2 +
 *         09-RESEARCH.md "Report-abuse flow" schema.
 *
 * User-submitted abuse reports targeting any reportable entity. Morph-style
 * target_type/target_id columns admit BOTH UUID PK targets (Clan, Player,
 * Article, GameMatch) AND any future bigint PK targets — target_id stored
 * as text/string for that flexibility.
 *
 * Status state machine:
 *   pending → dismissed (moderator: no action needed)
 *   pending → actioned (moderator: sanction issued / content removed / etc.)
 *
 * Column inventory:
 *   - id                  bigint pk
 *   - reporter_user_id    uuid FK users.id cascadeOnDelete (anonymise on user
 *                         deletion is out-of-scope v1; cascade is per plan)
 *   - target_type         text — FQN (e.g. 'App\\Models\\Clan', 'App\\Models\\GameMatch')
 *                                — Phase 4 D-04-03-A LOCKED uses GameMatch (NOT Match —
 *                                'match' is a PHP 8 reserved keyword)
 *   - target_id           text — UUID or bigint id stringified — admits both PK types
 *   - reason_code         text — harassment | spam | cheating |
 *                                inappropriate_content | other (enum validated in
 *                                FormRequest plan 09-11, NOT a DB CHECK to allow
 *                                future code additions without schema churn)
 *   - body                text nullable — optional narrative
 *   - status              text default 'pending'
 *   - reviewed_by_user_id uuid FK users.id nullable — reviewing moderator
 *   - reviewed_at         timestamptz nullable
 *   - review_notes        text nullable
 *   - timestamps          timestamptz
 *
 * Indexes (RESEARCH supporting indexes):
 *   ar_status_created_idx  (status, created_at) — moderator queue oldest-first.
 *   ar_target_idx          (target_type, target_id) — "reports against this clan".
 *   ar_reporter_idx        (reporter_user_id) — user history view.
 *
 * Threat refs:
 *   T-09-02-03 (DoS via flood of reports) — plan 09-11 adds rate limiter
 *     (5/hour by user_id); auth required (no anonymous reports v1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abuse_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_type');
            $table->string('target_id');
            $table->string('reason_code');
            $table->text('body')->nullable();
            $table->string('status')->default('pending');
            $table->foreignUuid('reviewed_by_user_id')->nullable()->constrained('users');
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'ar_status_created_idx');
            $table->index(['target_type', 'target_id'], 'ar_target_idx');
            $table->index('reporter_user_id', 'ar_reporter_idx');
        });

        DB::statement("ALTER TABLE abuse_reports ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE abuse_reports ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('abuse_reports');
    }
};
