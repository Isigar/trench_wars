<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .docs/05-database-schema.md § Clans § clan_invites + RESEARCH.md Pattern 6.
 *
 * State machine: pending → accepted | declined | revoked | expired
 *
 * `invited_user_id`  — the player being invited
 * `inviting_user_id` — the leader/officer who sent the invite
 * `decided_at`       — when the player accepted/declined (or system expired)
 * `expires_at`       — optional TTL; worker marks expired when now() > expires_at
 * `message`          — up to 500 chars; max enforced at FormRequest layer
 *
 * FKs all restrictOnDelete to preserve the invitation audit trail. This means
 * you must transfer/remove invites before deleting a clan or user account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_invites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('clan_id');
            $table->uuid('invited_user_id');
            $table->uuid('inviting_user_id');
            $table->text('status')->default('pending');
            $table->text('message')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('clan_id')->references('id')->on('clans')->restrictOnDelete();
            $table->foreign('invited_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('inviting_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE clan_invites ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE clan_invites ADD CONSTRAINT clan_invites_status_check CHECK (status IN ('pending','accepted','declined','revoked','expired'));");
        DB::statement("ALTER TABLE clan_invites ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE clan_invites ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_invites');
    }
};
