<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/08-rcon-automation/08-08-PLAN.md must_haves.key_links #3 +
 *         <interfaces> MatchPlayerStatAggregator block (`Player::firstWhere('steam_id_64', ...)`).
 *
 * Adds the `steam_id_64` column to the `players` table — the column the RCON
 * MatchPlayerStatAggregator (plan 08-08) uses to resolve CRCON-emitted steam_id_64
 * values to Player rows. Without this column the aggregator's `firstWhere` lookup
 * throws "column players.steam_id_64 does not exist" and Pitfall 5 (orphan event
 * silently skipped) cannot operate.
 *
 * **Schema choice — nullable + UNIQUE:**
 *   - Nullable because P1 / Phase 2 / Phase 4 onboarding flows never collected a
 *     Steam ID; existing player rows MUST keep their NULL.
 *   - UNIQUE because a single Steam ID corresponds to at most one Player in this
 *     league (D-002 — Discord identity is canonical, Steam ID is a one-to-one
 *     correspondence; if two players share a Steam ID one is impersonating the
 *     other and must be resolved out-of-band).
 *   - Postgres allows multiple NULL values under a regular UNIQUE constraint, so
 *     the constraint does not block multiple players without a Steam ID.
 *   - `text` (NOT `bigint`) — Steam 64-bit IDs are emitted as decimal strings by
 *     CRCON and stored as `text` everywhere in the rcon-worker + match_events
 *     payloads (08-07 SUMMARY § Pino redact list); using `text` here matches
 *     the wire shape exactly (no integer overflow risk, no string<->int conversion
 *     at the lookup layer).
 *
 * Round-1 acceptance (08-13 plan deferred-items): players self-register their
 * steam_id_64 via the public profile editor in a v2 enhancement; for round-1
 * scrim acceptance, admins backfill steam IDs out-of-band before the booked match.
 * Orphan events (no matching Player.steam_id_64) are silently skipped per
 * Pitfall 5 / threat-register T-08-08-03 disposition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->text('steam_id_64')->nullable()->after('country_code');
            $table->unique('steam_id_64', 'players_steam_id_64_unique');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropUnique('players_steam_id_64_unique');
            $table->dropColumn('steam_id_64');
        });
    }
};
