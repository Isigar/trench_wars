<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/05-discord-bot-v1/05-02-PLAN.md task 1 + 05-RESEARCH.md Pattern 4.
 *
 * Durable outbox for every Discord-bound side effect. Web writes rows in `pending` state; the
 * bot polls `status=pending` (filtered via `scopeDispatchable` for `backoff_until <= now()`),
 * claims a row by transitioning it to `dispatching` (atomic UPDATE inside FOR UPDATE SKIP
 * LOCKED transaction in plan 05-04 BotApiOutboundController::claim), then transitions to
 * `sent` (with `sent_message_id`) or `failed` (with `last_error`) on completion.
 *
 * Column inventory:
 *   - id              uuid pk (default gen_random_uuid())
 *   - channel_id      text — Discord channel snowflake (text — D-002 precedent; JS Number overflows snowflakes)
 *   - message_type    text — enum-like: match_announce | role_sync | generic (CHECK constraint)
 *   - status          text — enum-like: pending | dispatching | sent | failed (CHECK constraint)
 *   - payload         jsonb NOT NULL — never null; placeholder rows write `{}`
 *   - attempts        smallint default 0
 *   - last_error      text nullable — full stack trace from failed dispatch (internal-only per T-05-02-03)
 *   - sent_message_id text nullable — Discord message snowflake after successful send
 *   - causer_user_id  uuid nullable FK users.id nullOnDelete — human attribution
 *   - backoff_until   timestamptz nullable — exponential backoff retry deadline
 *   - created_at/updated_at timestamptz
 *
 * Indexes:
 *   - (status, backoff_until)  → supports `scopeDispatchable` poll query in plan 05-04
 *   - message_type             → admin filter by type in Filament resource (plan 05-07)
 *
 * CHECK constraints (defence-in-depth vs Eloquent-only validation; T-05-02-01 + T-05-02-02):
 *   - status        ∈ {pending, dispatching, sent, failed}
 *   - message_type  ∈ {match_announce, role_sync, generic}
 *
 * Timestamps: created_at / updated_at emitted as plain `timestamp` by $table->timestamps(),
 * then ALTERed to timestamptz with UTC interpretation (project idiom — see clans, matches).
 * backoff_until uses native timestampTz directly (application-managed datetime).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_outbound_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('channel_id');
            $table->string('message_type', 32);
            $table->string('status', 16)->default('pending');
            $table->jsonb('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->text('sent_message_id')->nullable();
            $table->foreignUuid('causer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('backoff_until')->nullable();
            $table->timestamps();

            $table->index(['status', 'backoff_until'], 'doutmsg_status_backoff_idx');
            $table->index('message_type', 'doutmsg_type_idx');
        });

        DB::statement('ALTER TABLE discord_outbound_messages ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE discord_outbound_messages ADD CONSTRAINT doutmsg_message_type_chk CHECK (message_type IN ('match_announce','role_sync','generic'));");
        DB::statement("ALTER TABLE discord_outbound_messages ADD CONSTRAINT doutmsg_status_chk CHECK (status IN ('pending','dispatching','sent','failed'));");
        DB::statement("ALTER TABLE discord_outbound_messages ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE discord_outbound_messages ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_outbound_messages');
    }
};
