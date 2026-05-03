<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .docs/05-database-schema.md § Identity § player_privacy + D-018.
 *
 * Per D-018: per-section booleans + global tier. show_real_name defaults FALSE because
 * real-name disclosure is the single most sensitive lever (PII). Other booleans default
 * TRUE because they are derived from public-by-default data (clan tag, match result).
 *
 * The 1:1 cascadeOnDelete from players → player_privacy is intentional: privacy settings
 * are meaningless without the player they describe. Soft-deleted players retain their
 * privacy row (Player softDelete only NULLs deleted_at, doesn't run the FK cascade).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_privacy', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('player_id')->unique();
            $table->text('show_to')->default('community');
            $table->boolean('show_real_name')->default(false);
            $table->boolean('show_discord_tag')->default(true);
            $table->boolean('show_clan_history')->default(true);
            $table->boolean('show_match_history')->default(true);
            $table->boolean('show_stats')->default(true);
            $table->timestamps();

            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete()->cascadeOnUpdate();
        });

        DB::statement('ALTER TABLE player_privacy ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE player_privacy ADD CONSTRAINT player_privacy_show_to_check CHECK (show_to IN ('public','community','clan','private'));");
        DB::statement("ALTER TABLE player_privacy ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE player_privacy ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('player_privacy');
    }
};
