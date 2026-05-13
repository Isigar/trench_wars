<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-10-PLAN.md Task 2
 *         (Rule 3 - Blocking deviation).
 *
 * Phase 6 plan 06-10 introduces TWO new Discord outbound message types
 * enqueued by TournamentObserver:
 *   - tournament_announce         (created())
 *   - tournament_announce_update  (updated() — gated on wasChanged('status'))
 *
 * The Phase 6 plan 06-08 migration
 * (2026_05_15_100500_extend_discord_outbound_message_types_for_phase_6.php)
 * extended the CHECK constraint to add `bracket_result_announce`. This
 * migration extends it again to add the two tournament_* types. Postgres has
 * no ALTER CONSTRAINT … MODIFY for CHECK predicates; drop + add is the
 * canonical idiom (same pattern as plan 06-08's migration).
 *
 * Threat refs:
 *   - T-05-02-01 (defence-in-depth vs Eloquent-only validation) — preserved;
 *     the new constraint is still strict-enum-with-CHECK.
 *   - T-06-10-02 (Pitfall 7 — double-fire on cascade observer chain) —
 *     orthogonal; the observer's wasChanged('status') gate is the mitigation.
 *
 * Naming: `tournament_announce` + `tournament_announce_update` mirror the
 * existing `match_announce` + `match_announce_update` convention (subject +
 * verb, snake_case; update variant for status-flip edits).
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
            . "'tournament_announce','tournament_announce_update'"
            . '));'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE discord_outbound_messages DROP CONSTRAINT IF EXISTS doutmsg_message_type_chk;');
        DB::statement(
            'ALTER TABLE discord_outbound_messages ADD CONSTRAINT doutmsg_message_type_chk '
            . "CHECK (message_type IN ('match_announce','role_sync','generic','bracket_result_announce'));"
        );
    }
};
