<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/09-polish/09-02-PLAN.md task 1 +
 *         09-RESEARCH.md Pitfall 7 (LOCKED — Laravel polymorphic notifications
 *         table MUST use uuidMorphs because users.id is uuid; vendor-published
 *         bigIncrements morphs would silently fail at insert).
 *
 * Laravel-style polymorphic notifications table — durable backing store for
 * `Illuminate\Notifications\DatabaseNotification`. Each row is one delivered
 * notification to one notifiable subject (typically a User).
 *
 * Column inventory:
 *   - id              uuid pk (Laravel convention — DatabaseNotification expects uuid)
 *   - notifiable_type text  — FQN of polymorphic target (e.g. App\Models\User)
 *   - notifiable_id   uuid  — target row id (users.id today; extensible)
 *   - type            text  — Notification class FQN OR databaseType() discriminator
 *                             (Pitfall 4 dedupe — indexed for fast count queries)
 *   - data            jsonb — Notification::toArray() payload (Postgres jsonb for
 *                             indexable querying, not text — RESEARCH "Database
 *                             indexes added in Phase 9")
 *   - read_at         timestamptz nullable — set when user marks read
 *   - created_at/updated_at timestamptz
 *
 * Indexes (RESEARCH "Database indexes added in Phase 9" — composite to support
 *  the two hot reads: list-unread and list-all-recent):
 *   - notifications_unread_idx  (notifiable_type, notifiable_id, read_at)
 *       → WHERE notifiable_id=? AND read_at IS NULL  (bell badge count)
 *   - notifications_recent_idx  (notifiable_type, notifiable_id, created_at)
 *       → WHERE notifiable_id=? ORDER BY created_at DESC LIMIT 20 (bell list)
 *   - notifications_type_idx    (type)
 *       → Pitfall 4 dedupe: count existing notifications of same type before
 *         dispatching (plan 09-04 NotificationDispatcher idempotency).
 *
 * Threat refs:
 *   T-09-02-01 (Tampering of notifications.data jsonb) — writes restricted to
 *     Notification class path; admin UI is read-only (CLAUDE.md §6 / activity_log
 *     idiom mirrored in plan 09-07).
 *   T-09-02-06 (Information disclosure via polymorphic id enumeration) — accepted;
 *     notifiable_id is uuid (not enumerable), and the bell controller (plan 09-06)
 *     scopes queries to auth()->id() exclusively.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('notifiable'); // emits notifiable_type/notifiable_id + composite index
            $table->string('type');
            $table->jsonb('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestamps();

            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at'],
                'notifications_unread_idx'
            );
            $table->index(
                ['notifiable_type', 'notifiable_id', 'created_at'],
                'notifications_recent_idx'
            );
            $table->index('type', 'notifications_type_idx');
        });

        DB::statement('ALTER TABLE notifications ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE notifications ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE notifications ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
