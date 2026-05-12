<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .docs/05-database-schema.md § Clans § clan_applications + RESEARCH.md Pattern 6.
 *
 * State machine: pending → accepted | declined | cancelled
 *
 * `applicant_user_id` — the player applying to join
 * `decided_by`        — the leader/officer who accepted or declined (nullable on cancel)
 * `decided_at`        — timestamp of the decision
 * `message`           — optional cover message from the applicant
 *
 * FKs:
 *   clan_id + applicant_user_id → restrictOnDelete (preserve application audit trail)
 *   decided_by → nullOnDelete (if the deciding admin leaves, preserve the decision record
 *                              with a null reference — mirrors invited_by pattern in memberships)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('clan_id');
            $table->uuid('applicant_user_id');
            $table->text('status')->default('pending');
            $table->text('message')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->uuid('decided_by')->nullable();
            $table->timestamps();

            $table->foreign('clan_id')->references('id')->on('clans')->restrictOnDelete();
            $table->foreign('applicant_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE clan_applications ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE clan_applications ADD CONSTRAINT clan_applications_status_check CHECK (status IN ('pending','accepted','declined','cancelled'));");
        DB::statement("ALTER TABLE clan_applications ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE clan_applications ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_applications');
    }
};
