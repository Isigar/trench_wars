<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .docs/05-database-schema.md § Clans § clans.
 *
 * `description` is jsonb for spatie/laravel-translatable HasTranslations (plan 02-03).
 * Soft deletes per schema convention (user-facing entity).
 * `status` constrained via CHECK (cheaper than Postgres ENUM to change later).
 * `owner_user_id` is restrictOnDelete: don't cascade-delete a clan when its owner
 * account is removed — admin must explicitly handle ownership transfer first.
 * `tag` is UNIQUE: clans compete for short identifiers globally (e.g. `[91st]`).
 * `accent_color` column is NOT added in Phase 2 — deferred per RESEARCH.md "Deferred Ideas".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('slug')->unique();
            $table->text('tag')->unique();
            $table->text('name');
            $table->jsonb('description')->nullable();
            $table->text('country_code')->nullable();
            $table->uuid('owner_user_id');
            $table->text('status')->default('active');
            $table->text('discord_role_id')->nullable();
            $table->text('discord_announce_channel_id')->nullable();
            $table->timestamps();
            $table->softDeletes('deleted_at');

            $table->foreign('owner_user_id')->references('id')->on('users')->restrictOnDelete()->cascadeOnUpdate();
        });

        DB::statement('ALTER TABLE clans ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE clans ADD CONSTRAINT clans_status_check CHECK (status IN ('active','suspended','disbanded'));");
        DB::statement("ALTER TABLE clans ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE clans ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE clans ALTER COLUMN deleted_at TYPE timestamptz USING deleted_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('clans');
    }
};
