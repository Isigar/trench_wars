// Plan 08-10 task 1 — replaces the Wave-0 skeleton from plan 08-01.
// Source: .planning/phases/08-rcon-automation/08-10-PLAN.md <interfaces>.
// Source (event taxonomy): 08-RESEARCH.md § CRCON API → Log Action Types (AllLogTypes enum)
//                          + event-shape table (lines 521-534).
//
// normalise() maps a raw CRCON log entry to Trenchwars' canonical match_event_type
// shape (mirrors apps/web/lang/en/rcon.php events.types.*). The returned
// `crcon_stream_id` is the idempotency key for the match_events table — plan
// 08-07's MatchEventIngestService uses (match_id, crcon_stream_id) as the unique
// upsert key (D-019 + RESEARCH Pitfall 3).
//
// Returns `null` when the log entry should be silently dropped — every CRCON
// action outside the 7 we subscribe to. The CrconClient drops + INFO-logs.

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
    crcon_action: string;
    crcon_stream_id: string;
    occurred_at: string;
    payload: unknown;
}

/** Worker-side raw CRCON entry shape — duck-typed against `types.ts` LogEntry. */
interface CrconLogEntry {
    id: string;
    log: Record<string, unknown>;
}

/**
 * Map a CRCON log entry to the canonical NormalisedEvent shape, or null to drop.
 *
 * The 7 subscribed actions (RESEARCH Pattern 1, SUBSCRIBE_ACTIONS) map 1:1 to
 * canonical match_event_type values. Unknown actions return null — the worker
 * drops them silently with an INFO log (T-08-10-02 mitigation: graceful
 * fallthrough rather than crash).
 */
export function normalise(log: CrconLogEntry): NormalisedEvent | null {
    const inner = log.log;
    const action = String(inner.action ?? '');
    const tsMs = Number(inner.timestamp_ms ?? Date.now());
    const occurred_at = new Date(tsMs).toISOString();

    switch (action) {
        case 'MATCH START':
            return {
                event_type: 'game_start',
                crcon_action: action,
                crcon_stream_id: log.id,
                occurred_at,
                payload: {
                    map: inner.map ?? null,
                    mode: inner.mode ?? null,
                },
            };

        case 'MATCH ENDED':
            return {
                event_type: 'match_end',
                crcon_action: action,
                crcon_stream_id: log.id,
                occurred_at,
                payload: {
                    winning_team: inner.winning_team ?? null,
                    allies_score: inner.allies_score ?? null,
                    axis_score: inner.axis_score ?? null,
                    ended_at: occurred_at,
                },
            };

        case 'KILL':
            return {
                event_type: 'player_kill',
                crcon_action: action,
                crcon_stream_id: log.id,
                occurred_at,
                payload: {
                    killer: {
                        steam_id_64: inner.steam_id_64_1 ?? null,
                        name: inner.player ?? null,
                    },
                    victim: {
                        steam_id_64: inner.steam_id_64_2 ?? null,
                        name: inner.player2 ?? null,
                    },
                    weapon: inner.weapon ?? 'unknown',
                },
            };

        case 'TEAM KILL':
            return {
                event_type: 'player_team_kill',
                crcon_action: action,
                crcon_stream_id: log.id,
                occurred_at,
                payload: {
                    killer: {
                        steam_id_64: inner.steam_id_64_1 ?? null,
                        name: inner.player ?? null,
                    },
                    victim: {
                        steam_id_64: inner.steam_id_64_2 ?? null,
                        name: inner.player2 ?? null,
                    },
                    weapon: inner.weapon ?? 'unknown',
                },
            };

        case 'CONNECTED':
            return {
                event_type: 'player_connect',
                crcon_action: action,
                crcon_stream_id: log.id,
                occurred_at,
                payload: {
                    steam_id_64: inner.steam_id_64_1 ?? null,
                    name: inner.player ?? null,
                },
            };

        case 'DISCONNECTED':
            return {
                event_type: 'player_disconnect',
                crcon_action: action,
                crcon_stream_id: log.id,
                occurred_at,
                payload: {
                    steam_id_64: inner.steam_id_64_1 ?? null,
                    name: inner.player ?? null,
                },
            };

        case 'TEAMSWITCH':
            return {
                event_type: 'team_switch',
                crcon_action: action,
                crcon_stream_id: log.id,
                occurred_at,
                payload: {
                    steam_id_64: inner.steam_id_64_1 ?? null,
                    name: inner.player ?? null,
                    from_team: inner.from_team ?? null,
                    to_team: inner.to_team ?? null,
                },
            };

        default:
            // Unsubscribed action — caller drops with INFO log.
            return null;
    }
}
