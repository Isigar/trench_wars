<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Source: .planning/phases/08-rcon-automation/08-02-PLAN.md task 2 +
 *         07-02 / 06-08 / 06-10 / 05-XX canonical DROP+ADD CHECK idiom (Postgres
 *         has no ALTER CONSTRAINT … MODIFY for CHECK predicates).
 *
 * Phase 8 plan 08-08 adds ONE new Discord outbound message type:
 *   - match_result_announce  (enqueued by MatchResultService when an RCON-sourced
 *                             result lands AND `announce_in_discord = true`)
 *
 * The actual baseline constraint NAME is `doutmsg_message_type_chk` (set by the
 * original Phase 5 migration `2026_05_13_170625_create_discord_outbound_messages_table.php`)
 * — the plan's <interfaces> block referenced the Laravel-default name
 * `discord_outbound_messages_message_type_check` which does NOT exist. Aligning
 * with reality (Phase 7's plan 07-02 used the same Rule 1 deviation pattern).
 *
 * Current baseline value list (as of `2026_05_15_120400_..._article_announce.php`,
 * verified via `SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE
 *  conname='doutmsg_message_type_chk'`):
 *
 *   match_announce, role_sync, generic, bracket_result_announce,
 *   tournament_announce, tournament_announce_update, article_announce
 *
 * Plan must_haves line `discord_outbound_messages.message_type CHECK accepts
 * match_result_announce` — adding the 8th value to the current 7-value baseline.
 *
 * NOTE: The plan's <interfaces> block listed a different 6-value baseline
 * (`clan_announce, match_announce, signup_open, signup_reminder,
 *  tournament_announce, article_announce`). That value list does NOT match the
 * actual Phase 5-7 history. Same Rule 1 deviation as Phase 7 plan 07-02 — we
 * align with the actual on-disk baseline, not the plan's text.
 *
 * Threat refs:
 *   T-05-02-01 (defence-in-depth vs Eloquent-only validation): the new
 *     constraint stays strict-enum-with-CHECK.
 *   T-08-02-04 / DOWNGRADE SAFETY: down() restores the Phase 7 baseline
 *     verbatim — Phase 8 rollback returns to a known-good state.
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
            . "'tournament_announce','tournament_announce_update','article_announce',"
            . "'match_result_announce'"
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
            . "'tournament_announce','tournament_announce_update','article_announce'"
            . '));'
        );
    }
};
