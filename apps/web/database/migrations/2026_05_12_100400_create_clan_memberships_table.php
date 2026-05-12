<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .docs/05-database-schema.md § Clans § clan_memberships + D-009.
 *
 * The partial unique index on `(user_id) WHERE left_at IS NULL` is the single
 * authoritative enforcement point for D-009 "one active ClanMembership per player".
 * Laravel's Schema::unique() has no WHERE clause support — raw DB::statement required
 * (RESEARCH.md Pattern 1 / Pitfall 1).
 *
 * `joined_at` and `left_at` are native timestampTz columns (application-managed),
 * not the Eloquent-managed created_at/updated_at. The Eloquent timestamps get the
 * same timestamptz upgrade treatment as all Phase 1/2 tables.
 *
 * FKs:
 *   clan_id  → clans.id     restrictOnDelete (preserve membership history on clan suspend)
 *   user_id  → users.id     restrictOnDelete (preserve history; soft-delete the player instead)
 *   invited_by → users.id   nullOnDelete (inviter account removal: preserve the row, null the reference)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('clan_id');
            $table->uuid('user_id');
            $table->text('role')->default('recruit');
            $table->timestampTz('joined_at');
            $table->timestampTz('left_at')->nullable();
            $table->uuid('invited_by')->nullable();
            $table->timestamps();

            $table->foreign('clan_id')->references('id')->on('clans')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE clan_memberships ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE clan_memberships ADD CONSTRAINT clan_memberships_role_check CHECK (role IN ('leader','officer','member','recruit'));");
        // D-009: at most one active membership per user (partial unique index — WHERE clause not supported by Schema builder)
        DB::statement('CREATE UNIQUE INDEX clan_memberships_one_active ON clan_memberships (user_id) WHERE left_at IS NULL;');
        DB::statement("ALTER TABLE clan_memberships ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE clan_memberships ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS clan_memberships_one_active;');
        Schema::dropIfExists('clan_memberships');
    }
};
