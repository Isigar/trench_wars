<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .docs/05-database-schema.md § Discord § discord_guild + D-003.
 *
 * Singular table name (`discord_guild`, not `discord_guilds`) per D-003:
 * "One Discord guild for the league". The Eloquent model overrides the
 * default plural table name with `protected $table = 'discord_guild'`.
 *
 * Single-row enforcement is operational (seeder + no-Create Filament resource)
 * rather than a DB CHECK constraint per RESEARCH.md Pattern 4 recommendation
 * and planner decision (A2 — seeder + no-Create is sufficient for operational safety).
 *
 * guild_id is nullable at creation: admin fills it after Discord bot setup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_guild', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('guild_id')->nullable()->unique();
            $table->text('name')->nullable();
            $table->text('icon_url')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE discord_guild ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE discord_guild ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE discord_guild ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_guild');
    }
};
