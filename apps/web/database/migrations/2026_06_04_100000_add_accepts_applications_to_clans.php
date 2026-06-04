<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: 10-01-PLAN.md Task 1 — CLAN-04 recruiting toggle + CLAN-03 duplicate-pending defence.
 *
 * `clans.accepts_applications` (boolean, default true):
 *   - Default TRUE — existing clans accept applications without any operator action.
 *   - Leaders opt OUT by setting this to false (plan 10-04 MyClan settings).
 *   - Applications to a clan with accepts_applications=false are rejected by
 *     ClanApplicationService::apply() (plan 10-02), thrown as ClanNotRecruitingException.
 *
 * `clan_applications_one_pending_per_clan` partial unique index:
 *   - CLAN-03 last-line DB-layer defence behind the service guard (plan 10-02).
 *   - Enforces (applicant_user_id, clan_id) uniqueness WHERE status='pending'.
 *   - Schema::unique() cannot express WHERE — raw DB::statement required
 *     (mirrors D-009 partial-unique idiom from 2026_05_12_100400_create_clan_memberships_table.php).
 *
 * down() drops in REVERSE order — index first, then column.
 *
 * Threat refs: T-10-01-01 (race-condition duplicate-pending), T-10-01-02 (mass-assignment — fillable here,
 * gated by ClanPolicy::update in plan 10-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table): void {
            $table->boolean('accepts_applications')->default(true)->after('status');
        });

        // CLAN-03: at most one pending application per (applicant, clan) pair.
        // Partial unique index — WHERE clause not supported by Schema builder.
        DB::statement("CREATE UNIQUE INDEX clan_applications_one_pending_per_clan ON clan_applications (applicant_user_id, clan_id) WHERE status = 'pending';");
    }

    public function down(): void
    {
        // Drop in REVERSE order — index first, then column.
        DB::statement('DROP INDEX IF EXISTS clan_applications_one_pending_per_clan;');

        Schema::table('clans', function (Blueprint $table): void {
            $table->dropColumn('accepts_applications');
        });
    }
};
