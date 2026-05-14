<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Source: .planning/phases/07-cms/07-02-PLAN.md task 1(e).
 *
 * Phase 7 plan 07-06 introduces ONE new Discord outbound message type
 *   - article_announce
 * enqueued by ArticleObserver on draft→published transition.
 *
 * Idiom: Phase 5/6 canonical DROP+ADD CHECK pattern (Postgres has no
 * ALTER CONSTRAINT … MODIFY for CHECK predicates). Same shape as plans
 * 06-08 + 06-10.
 *
 * Existing constraint value list (as of Wave 1 baseline; verified via
 * `SELECT pg_get_constraintdef(...) FROM pg_constraint WHERE
 *  conname='doutmsg_message_type_chk'`):
 *
 *   - match_announce             (Phase 5 plan 05-XX — original creation)
 *   - role_sync                  (Phase 5 plan 05-XX — original creation)
 *   - generic                    (Phase 5 plan 05-XX — original creation)
 *   - bracket_result_announce    (Phase 6 plan 06-08)
 *   - tournament_announce        (Phase 6 plan 06-10)
 *   - tournament_announce_update (Phase 6 plan 06-10)
 *
 * NOTE: Plan 07-02 must_haves line 24 references a 7th value
 * `match_announce_update` between `match_announce` and `role_sync`. That
 * value is referenced in plan 06-10's comment block but was NEVER added
 * to the actual constraint by any migration. We align the up() set with
 * the actual baseline (6 → 7 values, not 7 → 8). Recorded as Rule 1
 * deviation in 07-02-SUMMARY.
 *
 * Threat refs:
 *   T-07-02-05 (downgrade safety): down() restores the Phase 6 baseline
 *     verbatim — Phase 7 rollback returns to a known-good state.
 *   T-05-02-01 (defence-in-depth vs Eloquent-only validation): the new
 *     constraint is still strict-enum-with-CHECK.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE discord_outbound_messages DROP CONSTRAINT IF EXISTS doutmsg_message_type_chk;');
        DB::statement(
            'ALTER TABLE discord_outbound_messages ADD CONSTRAINT doutmsg_message_type_chk '
            . 'CHECK (message_type IN ('
            . "'match_announce','role_sync','generic','bracket_result_announce',"
            . "'tournament_announce','tournament_announce_update','article_announce'"
            . '));'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE discord_outbound_messages DROP CONSTRAINT IF EXISTS doutmsg_message_type_chk;');
        DB::statement(
            'ALTER TABLE discord_outbound_messages ADD CONSTRAINT doutmsg_message_type_chk '
            . 'CHECK (message_type IN ('
            . "'match_announce','role_sync','generic','bracket_result_announce',"
            . "'tournament_announce','tournament_announce_update'"
            . '));'
        );
    }
};
