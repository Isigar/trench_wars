<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Source: .planning/phases/09-polish/09-02-PLAN.md task 2 + Pitfall 10 (extend
 *         discord_outbound_messages CHECK to permit the new `user_dm` message
 *         type) +
 *         07-02 / 06-08 / 06-10 / 05-XX / 08-02 canonical DROP+ADD CHECK idiom
 *         (Postgres has no ALTER CONSTRAINT … MODIFY for CHECK predicates).
 *
 * Phase 9 introduces ONE new Discord outbound message type:
 *   - user_dm  (enqueued by NotificationService -> DiscordChannel when the
 *               'discord' channel is enabled in user_notification_preferences;
 *               bot dispatches to the user's DM channel rather than a guild
 *               channel — see plan 09-03 DiscordChannelOutboxTest stub).
 *
 * The actual baseline constraint NAME is `doutmsg_message_type_chk` (set by the
 * Phase 5 migration `2026_05_13_170625_create_discord_outbound_messages_table.php`)
 * — the plan's <interfaces> block referenced the Laravel-default name
 * `discord_outbound_messages_message_type_check` which does NOT exist. Aligning
 * with reality (Phase 7's plan 07-02 + Phase 8's plan 08-02 used the same Rule 1
 * deviation pattern).
 *
 * Current baseline value list (verified via
 *   SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname='doutmsg_message_type_chk'
 * after Phase 8 plan 08-02):
 *
 *   match_announce, role_sync, generic, bracket_result_announce,
 *   tournament_announce, tournament_announce_update, article_announce,
 *   match_result_announce
 *
 * Adding the 9th value `user_dm`.
 *
 * Threat refs:
 *   T-09-02-05 (Tampering of CHECK constraint) — DB-level CHECK is strict
 *     defence-in-depth; new types MUST be added via migration (auditable).
 *   DOWNGRADE SAFETY: down() restores the Phase 8 baseline verbatim — Phase 9
 *     rollback returns to a known-good state.
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
            . "'match_result_announce','user_dm'"
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
            . "'tournament_announce','tournament_announce_update','article_announce',"
            . "'match_result_announce'"
            . '));'
        );
    }
};
