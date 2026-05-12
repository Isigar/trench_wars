<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .docs/05-database-schema.md § Clans § clan_clan_tag (pivot) + D-008.
 *
 * Pivot table: no `id` column; composite primary key `[clan_id, clan_tag_id]`.
 * Only `created_at` — no `updated_at` (attach/detach pattern, no updates on pivot rows).
 * Both FKs use `restrictOnDelete`: tags cannot be silently removed from the system
 * while clans are attached to them — admin must detach first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_clan_tag', function (Blueprint $table): void {
            $table->uuid('clan_id');
            $table->uuid('clan_tag_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['clan_id', 'clan_tag_id']);

            $table->foreign('clan_id')->references('id')->on('clans')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('clan_tag_id')->references('id')->on('clan_tags')->restrictOnDelete()->cascadeOnUpdate();
        });

        DB::statement("ALTER TABLE clan_clan_tag ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_clan_tag');
    }
};
