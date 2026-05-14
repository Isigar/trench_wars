<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/07-cms/07-02-PLAN.md task 1(a).
 *
 * categories table — taxonomy for articles (Phase 7 CMS).
 *
 *  - uuid PK with DB-side gen_random_uuid() default (Phase 2 idiom — belt-and-
 *    braces for seeders that use DB::table rather than Eloquent).
 *  - slug text UNIQUE (non-translatable per Open Question 5 LOCKED inline:
 *    single-slug v1, no per-locale routing — D-013 ships EN at launch).
 *  - name jsonb (D-013 translatable via spatie/laravel-translatable HasTranslations
 *    in plan 07-03). JSONB chosen over plain JSON for indexability later.
 *  - softDeletes — categories retain history so articles can reference the
 *    deleted taxonomy row without losing data integrity.
 *
 * Down: dropIfExists handles trigger/index drops via Postgres cascade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 200)->unique();
            $table->jsonb('name');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });

        DB::statement('ALTER TABLE categories ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE categories ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE categories ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE categories ALTER COLUMN deleted_at TYPE timestamptz USING deleted_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
