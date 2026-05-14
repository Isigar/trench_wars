<?php

declare(strict_types=1);

namespace App\Data\Internal;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/08-rcon-automation/08-06-PLAN.md <interfaces> MatchEventInputData.
 *
 * Wire-format INPUT shape for POST /api/internal/match/{match}/events. The worker
 * (plan 08-10) emits this DTO; web's StoreMatchEventsRequest validates the array
 * shape and MatchEventIngestService (plan 08-07) persists it as a MatchEvent row.
 *
 * `#[TypeScript]` (D-020) — plan 08-12 regenerates packages/shared-types/src/api.d.ts
 * and the rcon-worker (apps/rcon-worker) imports this type for compile-time parity.
 *
 * **Timestamps are ISO-8601 strings, NOT Carbon.** Wire serialisation must be
 * deterministic — Carbon's default __toString varies by locale + microseconds. The
 * worker sends `event.occurred_at = new Date().toISOString()`; web casts to
 * Carbon at the persistence boundary inside MatchEventIngestService.
 *
 * Per-field rationale:
 *  - `crcon_stream_id` nullable — CRCON resumes its `id` stream across reconnects,
 *    but the worker also synthesises ad-hoc events (`manual_error` during normaliser
 *    misses) that have no upstream id. The composite UNIQUE `(match_id, crcon_stream_id)`
 *    treats NULL as distinct so manual_error rows never collide.
 *  - `event_type` MUST be one of the 10 canonical values — enforced by
 *    StoreMatchEventsRequest::rules() AND the DB CHECK constraint as defence in depth.
 *  - `crcon_action` nullable — the raw CRCON action string preserved for debugging
 *    (when the normaliser maps `TEAM KILL: Foo -> Bar` → event_type=player_team_kill,
 *    crcon_action="TEAM KILL"). NULL for synthesised events.
 *  - `payload` is an opaque JSON shape that varies per event_type — see
 *    08-04-PLAN.md MatchEvent <interfaces> for canonical per-type payloads.
 */
#[TypeScript]
final class MatchEventInputData extends Data
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public ?string $crcon_stream_id,
        public string $event_type,
        public ?string $crcon_action,
        public array $payload,
        public string $occurred_at,
    ) {}
}
