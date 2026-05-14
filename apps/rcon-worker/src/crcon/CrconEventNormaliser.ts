// Wave 0 skeleton — Phase 8 plan 08-01 task 1.
// Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 1 behaviour list.
// Source (event taxonomy): 08-RESEARCH.md § CRCON API → Log Action Types (AllLogTypes enum).
//
// normalise() maps a raw CRCON log entry to Trenchwars' canonical match_event_type
// shape (see lang/en/rcon.php events.types.*). The returned `crcon_stream_id` is the
// idempotency key for the match_events table — plan 08-07's MatchEventIngestService
// uses (match_id, crcon_stream_id) as the unique upsert key (D-019 + RESEARCH Pitfall 3).
//
// Returns null when the log entry should be silently dropped (e.g. unrelated
// chat lines that don't map to a canonical match_event_type). Real implementation
// lands in plan 08-10 (worker side) — Wave 0 ships the contract only.

/** Canonical match event types — mirrors lang/en/rcon.php `events.types.*`. */
export type CanonicalEventType =
    | 'game_start'
    | 'round_start'
    | 'player_kill'
    | 'player_team_kill'
    | 'player_connect'
    | 'player_disconnect'
    | 'team_switch'
    | 'round_end'
    | 'match_end'
    | 'manual_error';

export interface NormalisedEvent {
    event_type: CanonicalEventType;
    payload: unknown;
    crcon_stream_id: string;
    occurred_at: string;
    crcon_action: string;
}

/**
 * Map a CRCON log entry to the canonical NormalisedEvent shape, or null to drop.
 *
 * @param _crconLog Raw entry as received from the CRCON `/ws/logs` stream.
 */
export function normalise(_crconLog: unknown): NormalisedEvent | null {
    throw new Error('CrconEventNormaliser.normalise not implemented — replaced by plan 08-10');
}
