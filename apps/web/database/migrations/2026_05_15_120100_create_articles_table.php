<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/07-cms/07-02-PLAN.md task 1(b).
 *
 * articles table — Phase 7 CMS editorial entity.
 *
 *  - uuid PK with DB-side gen_random_uuid() default (Phase 2 idiom).
 *  - slug text UNIQUE — author-provided (Open Question 4 LOCKED inline: no
 *    auto-suffix; FormRequest unique-rule surfaces violation as Filament
 *    validation error to preserve permalink integrity).
 *  - category_id uuid FK -> categories.id restrictOnDelete (Phase 2 idiom —
 *    category deletion blocked while articles still reference it).
 *  - title/excerpt/body jsonb — D-013 translatable via spatie/laravel-translatable
 *    in plan 07-03. body is required (NOT NULL) since publish workflow demands
 *    content; excerpt is optional (for index page card layout).
 *  - status text + CHECK constraint (3 values: draft, scheduled, published).
 *    The CHECK is added AFTER Schema::create() — Postgres requires
 *    ADD CONSTRAINT for declared CHECKs (Phase 4 idiom).
 *  - scheduled_at/published_at timestamptz nullable — observer (plan 07-06)
 *    writes published_at on draft→published transition.
 *  - author_user_id uuid FK -> users.id nullOnDelete — author preserved when
 *    user account is soft-deleted (provenance trail).
 *  - allow_discord_announce boolean default true — per-article opt-out for
 *    Discord announce (Open Question 1 LOCKED inline: routes to global
 *    config('discord.league_announce_channel_id') in v1).
 *  - softDeletes — articles retain history; observer enforces append-only
 *    audit trail (plan 07-12).
 *  - index(status, published_at) — supports public listing query
 *    WHERE status='published' ORDER BY published_at DESC (plan 07-09).
 *
 * Threat refs:
 *   T-07-02-01 (slug uniqueness race) — DB-level UNIQUE is defence-in-depth
 *     for the Filament FormRequest unique-rule.
 *   T-07-02-02 (status CHECK bypass) — Postgres CHECK enforces the 3 values
 *     regardless of Eloquent path; observer adds the only-forward state
 *     machine on top.
 *
 * Down: dropIfExists drops the table + its CHECK + FKs via Postgres cascade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 200)->unique();
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->jsonb('title');
            $table->jsonb('excerpt')->nullable();
            $table->jsonb('body');
            $table->string('status', 20)->default('draft');
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignUuid('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('allow_discord_announce')->default(true);
            $table->timestamps();
            $table->softDeletes('deleted_at');

            $table->index(['status', 'published_at'], 'articles_status_published_at_idx');
        });

        DB::statement('ALTER TABLE articles ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement(
            'ALTER TABLE articles ADD CONSTRAINT articles_status_chk '
            . "CHECK (status IN ('draft','scheduled','published'));"
        );
        DB::statement("ALTER TABLE articles ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE articles ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE articles ALTER COLUMN deleted_at TYPE timestamptz USING deleted_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
