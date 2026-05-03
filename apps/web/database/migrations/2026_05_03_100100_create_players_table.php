<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .docs/05-database-schema.md § Identity § players.
 * Soft deletes per "Convention" block at the top of that doc.
 *
 * `bio` is jsonb so it can be wrapped by spatie/laravel-translatable's HasTranslations
 * trait in Phase 2+ without a schema change. P1 stores plain JSON; the trait wraps it later.
 *
 * avatar_source is constrained via CHECK to enforce the documented enum without needing
 * a Postgres ENUM type (CHECK is cheaper to migrate than ALTER TYPE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->text('slug')->unique();
            $table->text('display_name')->nullable();
            $table->text('avatar_source')->default('discord');
            $table->text('avatar_path')->nullable();
            $table->jsonb('bio')->nullable();
            $table->text('country_code')->nullable();
            $table->timestamps();
            $table->softDeletes('deleted_at');

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete()->cascadeOnUpdate();
        });

        DB::statement('ALTER TABLE players ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE players ADD CONSTRAINT players_avatar_source_check CHECK (avatar_source IN ('discord','upload'));");
        DB::statement("ALTER TABLE players ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE players ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
