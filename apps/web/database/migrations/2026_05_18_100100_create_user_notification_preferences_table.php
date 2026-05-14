<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/09-polish/09-02-PLAN.md task 1 +
 *         09-RESEARCH.md Notifications section (event_type × channel matrix).
 *
 * Per-user × event_type × channel preference toggle. One row per
 * (user_id, event_type, channel) tuple; missing rows fall back to the
 * application-level default (`enabled=true`) decided in plan 09-03
 * UserNotificationPreferences service.
 *
 * Column inventory:
 *   - id         bigint pk — preferences are internal config rows, not user-visible;
 *                no need for UUID PK (Phase 4 idiom: pivot/config tables use bigint).
 *   - user_id    uuid FK users.id cascadeOnDelete — user deletion cleans up prefs
 *   - event_type text — application-validated enum (plan 09-03):
 *                        match_starting_soon | match_cancelled | match_result_published
 *                        | clan_application_decided | clan_invite_received
 *                       (NOT enforced via DB CHECK to stay portable; Pest test in 09-03)
 *   - channel    text — database | discord (NOT enforced via DB CHECK; Pest test in 09-03)
 *   - enabled    boolean default true
 *   - timestamps timestamptz
 *
 * Unique:
 *   unp_unique (user_id, event_type, channel) — one toggle per tuple
 *
 * Index:
 *   user_id — supports `WHERE user_id = ?` lookup before dispatch (plan 09-04).
 *
 * Threat refs: none — internal config table, no user-visible PK enumeration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('channel');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'event_type', 'channel'], 'unp_unique');
            $table->index('user_id', 'unp_user_id_idx');
        });

        DB::statement("ALTER TABLE user_notification_preferences ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE user_notification_preferences ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
