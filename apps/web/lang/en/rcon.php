<?php

declare(strict_types=1);

/*
| Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2 behaviour list
| (events.types / errors / audit groups) + 08-RESEARCH.md § CRCON API → Log Action Types
| (AllLogTypes enum). D-013 — every t()/__() consumed by Phase 8 (MatchEventNormaliser,
| ValidateRconHmacSignature, MatchServerResource TestConnectionAction) resolves to a key
| here from day one. CI gate: NoHardcodedStringsTest (Phase 8 plan 08-12).
|
| Naming: snake_case, hierarchical groups. event_type values mirror the canonical
| match_event_type column values from plan 08-02 migration (event_types.* on the model).
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Canonical match_event_type labels
    |--------------------------------------------------------------------------
    | Consumed by:
    |   - MatchEventNormaliser (web side, plan 08-07) for the contract check
    |   - MatchEventIngestService for human-readable event audit
    |   - apps/rcon-worker/src/crcon/CrconEventNormaliser.ts (TS mirror, plan 08-10)
    */
    'events' => [
        'types' => [
            'game_start' => 'Game started',
            'round_start' => 'Round started',
            'player_kill' => 'Player killed',
            'player_team_kill' => 'Team kill',
            'player_connect' => 'Player connected',
            'player_disconnect' => 'Player disconnected',
            'team_switch' => 'Team switch',
            'round_end' => 'Round ended',
            'match_end' => 'Match ended',
            'manual_error' => 'Manual error recorded',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error messages (HTTP + worker + middleware surfaces)
    |--------------------------------------------------------------------------
    | Used by:
    |   - ValidateRconHmacSignature middleware (plan 08-05) — bad/stale signatures
    |   - rcon-worker logs on connection failure (Pino redacted)
    |   - MatchServerResource TestConnectionAction error toast
    */
    'errors' => [
        'unreachable' => 'CRCON server is unreachable — connection refused or timed out.',
        'auth_failed' => 'CRCON authentication failed — check RCON password.',
        'permission_denied' => 'CRCON RCON key lacks log-stream permission.',
        'replayed_nonce' => 'Replay detected — signature nonce already consumed.',
        'stale_signature' => 'Signature timestamp outside the ±60s replay window.',
        'bad_signature' => 'HMAC signature verification failed.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit-log copy
    |--------------------------------------------------------------------------
    | Filament admin panel + activity_log description templates. Drives the
    | "Audit log" view (plan 01-13) when Phase 8 events arrive.
    */
    'audit' => [
        'manual_override_wins' => 'Manual MatchResult overrides incoming RCON match_end event (locked).',
        'rcon_arrived_locked' => 'RCON match_end arrived after manual override — event recorded but result row left untouched.',
        'test_connection_queued' => 'TestConnection queued for :server — awaiting worker.',
        'test_connection_ok' => 'TestConnection succeeded — :server reachable + log stream open.',
        'test_connection_error' => 'TestConnection failed for :server — :reason.',
    ],
];
