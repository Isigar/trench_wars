<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .docs/05-database-schema.md § Identity § users.
 * Discord IDs are 64-bit snowflakes that overflow JS Number — store as `text` (D-002).
 *
 * Email column is `citext` (case-insensitive). Schema builder doesn't have citext, so we
 * add it via raw SQL after the Schema::create call. id default is set to gen_random_uuid()
 * at the DB level as belt-and-braces in case the Eloquent model isn't responsible for ID
 * generation (e.g. seeders using DB::table). created_at/updated_at are upgraded to
 * timestamptz to match the rest of the schema doc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('discord_id')->unique();
            $table->text('username');

            // citext for case-insensitive email — added via raw SQL below.
            $table->text('avatar_url')->nullable();
            $table->text('locale')->default('en');
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampTz('left_community_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Add citext email column via raw SQL (Laravel Schema builder doesn't support citext directly).
        DB::statement('ALTER TABLE users ADD COLUMN email citext NULL;');

        // Default UUID via gen_random_uuid() at the DB level — belt-and-braces in case
        // the Eloquent model isn't responsible for ID generation (e.g. seeders using DB::table).
        DB::statement('ALTER TABLE users ALTER COLUMN id SET DEFAULT gen_random_uuid();');

        // Cast created_at/updated_at to timestamptz for parity with the rest of the schema.
        DB::statement("ALTER TABLE users ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE users ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
