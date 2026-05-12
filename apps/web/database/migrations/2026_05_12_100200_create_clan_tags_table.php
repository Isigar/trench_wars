<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .docs/05-database-schema.md § Clans § clan_tags + D-008.
 *
 * `label` is jsonb for spatie/laravel-translatable HasTranslations (plan 02-03).
 * `color` stores a hex colour string e.g. `#FF6B00` — validated at form layer.
 * Tags are m:n to clans via `clan_clan_tag` pivot (D-008).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('slug')->unique();
            $table->jsonb('label');
            $table->text('color')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE clan_tags ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE clan_tags ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE clan_tags ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_tags');
    }
};
