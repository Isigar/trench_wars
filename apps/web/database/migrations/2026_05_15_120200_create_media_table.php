<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source: vendor:publish from spatie/laravel-medialibrary (plan 07-01 task 1).
 *
 * Phase 7 amendment (plan 07-02 task 1(c)):
 *   - File renamed from 2026_05_13_234858_create_media_table.php to the Wave 1
 *     timestamp 2026_05_15_120200 so the migration runs AFTER articles (Phase 5
 *     D-05-03-B / Pitfall 9 ordering — polymorphic media inserts reference
 *     articles.id in plan 07-03).
 *   - $table->morphs('model') -> $table->uuidMorphs('model'). Trenchwars uses
 *     uuid PKs project-wide (users, players, clans, articles all uuid); the
 *     vendor-published bigInteger morphs would silently fail at insert time
 *     when articles attach hero images via $article->addMediaFromRequest().
 *
 * Threat refs: none — table is internal medialibrary plumbing; file-system
 * facing fields (file_name, disk) are sanitised by the Spatie package itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();

            $table->uuidMorphs('model');
            $table->uuid()->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();

            $table->nullableTimestamps();
        });
    }
};
