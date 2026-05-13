<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 1 (match_access_rules) +
 *         04-02-PLAN.md <interfaces> match_access_rules block.
 *
 * Two-FK association: which clan_tags are allowed to sign up to this match.
 * Empty rule set = match is open to all eligible players (T-04-06 tag-restricted logic
 * is "no rule = allow all" / "has rules = allow only tagged"). The composite UNIQUE
 * `match_access_rules_unique` prevents duplicate (match, tag) rows.
 *
 * FK direction (RESEARCH Pattern 1):
 *   match_id     → matches     cascadeOnDelete  (match delete → rules delete)
 *   clan_tag_id  → clan_tags   restrictOnDelete  (tag delete blocked while in use)
 *
 * Note: id column is auto-promoted to PK via `$table->uuid('id')->primary()`; the
 * composite UNIQUE is a secondary index, not the PK (matches Phase 3 idiom).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_access_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('match_id');
            $table->uuid('clan_tag_id');
            $table->timestamps();

            $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
            $table->foreign('clan_tag_id')->references('id')->on('clan_tags')->restrictOnDelete();

            $table->unique(['match_id', 'clan_tag_id'], 'match_access_rules_unique');
            $table->index('match_id');
        });

        DB::statement('ALTER TABLE match_access_rules ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE match_access_rules ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE match_access_rules ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('match_access_rules');
    }
};
