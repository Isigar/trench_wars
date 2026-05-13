<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-08-PLAN.md Task 1
 *         (Rule 3 - Blocking deviation).
 *
 * Phase 6 plan 06-08 introduces a new Discord outbound message type
 * `bracket_result_announce` (enqueued by BracketAdvancementService::advance()
 * when a tournament match resolves and the bracket winner propagates).
 *
 * The original Phase 5 migration
 * (2026_05_13_170625_create_discord_outbound_messages_table.php) installed a
 * CHECK constraint restricting message_type to:
 *     match_announce | role_sync | generic
 *
 * Inserting `bracket_result_announce` against that constraint fails with
 * SQLSTATE 23514 (check_violation). This migration drops + re-creates the
 * CHECK to add the new value (Postgres has no ALTER CONSTRAINT … MODIFY for
 * CHECK predicates; drop + add is the canonical idiom).
 *
 * Threat refs:
 *   - T-05-02-01 (defence-in-depth vs Eloquent-only validation) — preserved;
 *     the new constraint is still strict-enum-with-CHECK.
 *   - T-06-08-07 (BracketAdvancementService circular DI) — orthogonal.
 *
 * Naming: `bracket_result_announce` mirrors the existing `match_announce`
 * convention (subject + verb, snake_case).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE discord_outbound_messages DROP CONSTRAINT IF EXISTS doutmsg_message_type_chk;');
        DB::statement(
            'ALTER TABLE discord_outbound_messages ADD CONSTRAINT doutmsg_message_type_chk '
            . "CHECK (message_type IN ('match_announce','role_sync','generic','bracket_result_announce'));"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE discord_outbound_messages DROP CONSTRAINT IF EXISTS doutmsg_message_type_chk;');
        DB::statement(
            'ALTER TABLE discord_outbound_messages ADD CONSTRAINT doutmsg_message_type_chk '
            . "CHECK (message_type IN ('match_announce','role_sync','generic'));"
        );
    }
};
