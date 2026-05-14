<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/09-polish/09-02-PLAN.md task 1 +
 *         09-RESEARCH.md "Moderator Tooling" schema +
 *         09-OPEN-QUESTIONS Q4 LOCKED (site-wide bans for v1, NO clan_id column).
 *
 * Site-wide ban table — issued by moderators/admins, enforced by middleware
 * (plan 09-07) gating all authenticated routes when an active ban row exists.
 *
 * "Active" criteria = lifted_at IS NULL AND (expires_at IS NULL OR expires_at > now()).
 *
 * Column inventory:
 *   - id                  bigint pk — Filament-friendly, no user-visible URL exposure
 *   - user_id             uuid FK users.id cascadeOnDelete — user deletion removes bans
 *   - ban_type            text — temporary | permanent (validated in plan 09-07
 *                                BanService; NOT a DB CHECK to keep schema portable —
 *                                Pest test in plan 09-07 enforces application-side)
 *   - reason              text — moderator-authored, internal-only (T-09-02-02 accept)
 *   - expires_at          timestamptz nullable — NULL = permanent
 *   - issued_by_user_id   uuid FK users.id — issuing moderator/admin (NO ondelete —
 *                              restrict by default; preserve audit trail)
 *   - lifted_at           timestamptz nullable — set when ban is lifted early
 *   - lifted_by_user_id   uuid FK users.id nullable — lifter (same provenance reason)
 *   - lift_reason         text nullable
 *   - timestamps          timestamptz
 *
 * Indexes:
 *   bans_user_expires_idx (user_id, expires_at) — hot path: "is this user banned now?"
 *                                                  scans by user_id then filters expires_at.
 *   bans_issued_by_idx (issued_by_user_id) — Filament filter "bans issued by me".
 *
 * Threat refs:
 *   T-09-02-02 (reason disclosure) — accepted; moderator-only Filament UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bans', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ban_type');
            $table->text('reason');
            $table->timestampTz('expires_at')->nullable();
            $table->foreignUuid('issued_by_user_id')->constrained('users');
            $table->timestampTz('lifted_at')->nullable();
            $table->foreignUuid('lifted_by_user_id')->nullable()->constrained('users');
            $table->text('lift_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at'], 'bans_user_expires_idx');
            $table->index('issued_by_user_id', 'bans_issued_by_idx');
        });

        DB::statement("ALTER TABLE bans ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE bans ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('bans');
    }
};
