<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source: .planning/phases/08-rcon-automation/08-02-PLAN.md task 1 +
 *         08-RESEARCH.md § Idempotency Pattern (Pitfall 3 — duplicate POST on worker
 *         reconnect must be a no-op via UNIQUE collision).
 *
 * Append-only stream of normalised CRCON events per match. Worker (plan 08-10)
 * normalises CRCON wire-format into the canonical event_type enum and POSTs to
 * /api/internal/match/{id}/events (HMAC-signed, plan 08-05). MatchEventIngestService
 * (plan 08-07) upserts on the composite UNIQUE — duplicate POSTs collide harmlessly.
 *
 * Column inventory:
 *   - id              uuid pk (default gen_random_uuid())
 *   - match_id        uuid FK matches.id cascadeOnDelete (events follow match lifecycle)
 *   - event_type      text NOT NULL — canonical match_event_type (CHECK constraint, 10 values)
 *   - crcon_action    text NULL    — raw CRCON action string preserved for debugging
 *   - crcon_stream_id text NULL    — CRCON `id` field on /ws/logs message (for resume +
 *                                    composite UNIQUE — see threat T-08-02-03)
 *   - payload         jsonb NOT NULL — normaliser output (player names, weapons, etc.)
 *   - occurred_at     timestamptz NOT NULL — when CRCON saw the event
 *   - ingested_at     timestamptz default now() — when web persisted it
 *
 * Composite UNIQUE (T-08-02-03 mitigation):
 *   match_events_match_stream_unique — (match_id, crcon_stream_id) — second INSERT
 *   raises UNIQUE violation; MatchEventIngestService catches and treats as no-op.
 *
 * Index (aggregator query):
 *   match_events_aggregator_idx — (match_id, event_type, occurred_at) — supports
 *   MatchPlayerStatAggregatorService scan-by-type-in-temporal-order pattern.
 *
 * CHECK constraint (canonical 10-value event enum):
 *   match_events_type_check — ('game_start','round_start','player_kill',
 *                              'player_team_kill','player_connect','player_disconnect',
 *                              'team_switch','round_end','match_end','manual_error')
 *   Mirrors lang/en/rcon.php events.types.* keys (Wave 0 plan 08-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('match_id');
            $table->text('event_type');
            $table->text('crcon_action')->nullable();
            $table->text('crcon_stream_id')->nullable();
            $table->jsonb('payload');
            $table->timestampTz('occurred_at');
            $table->timestampTz('ingested_at')->useCurrent();

            $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();

            $table->unique(['match_id', 'crcon_stream_id'], 'match_events_match_stream_unique');
            $table->index(['match_id', 'event_type', 'occurred_at'], 'match_events_aggregator_idx');
        });

        DB::statement('ALTER TABLE match_events ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE match_events ADD CONSTRAINT match_events_type_check CHECK (event_type IN ('game_start','round_start','player_kill','player_team_kill','player_connect','player_disconnect','team_switch','round_end','match_end','manual_error'));");
    }

    public function down(): void
    {
        Schema::dropIfExists('match_events');
    }
};
