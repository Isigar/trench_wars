// Plan 08-10 task 1 — replaces Wave-0 RED stub from plan 08-01.
// Source: .planning/phases/08-rcon-automation/08-10-PLAN.md task 1 behaviour list.
//
// 8 cases (one per mapping + 1 null-fallthrough), per the plan:
//   1. KILL → player_kill with killer+victim+weapon.
//   2. TEAM KILL → player_team_kill.
//   3. CONNECTED → player_connect with steam_id+name.
//   4. DISCONNECTED → player_disconnect.
//   5. TEAMSWITCH → team_switch with from/to.
//   6. MATCH START → game_start with map/mode.
//   7. MATCH ENDED → match_end with winning_team/scores/ended_at.
//   8. CHAT (unsubscribed) → null.
//
// Canonical event_type names mirror apps/web/lang/en/rcon.php events.types.*
// (cross-tier coupling — same names land in match_events.event_type column).
import { describe, expect, it } from 'vitest';
import { normalise } from '../../src/crcon/CrconEventNormaliser.js';

// Helper: a CRCON-shaped log entry with timestamp_ms = a fixed reproducible epoch.
const TS_MS = 1715670000000; // 2024-05-14T07:00:00.000Z — frozen-clock for deterministic ISO compare
const TS_ISO = new Date(TS_MS).toISOString();

function entry(id: string, action: string, extra: Record<string, unknown> = {}) {
    return {
        id,
        log: {
            action,
            timestamp_ms: TS_MS,
            ...extra,
        },
    };
}

describe('CrconEventNormaliser', () => {
    it('maps KILL → player_kill with killer+victim+weapon', () => {
        const out = normalise(
            entry('K-1', 'KILL', {
                steam_id_64_1: '76561198000000001',
                player: 'Alice',
                steam_id_64_2: '76561198000000002',
                player2: 'Bob',
                weapon: 'KAR98K',
            }),
        );
        expect(out).not.toBeNull();
        expect(out!.event_type).toBe('player_kill');
        expect(out!.crcon_action).toBe('KILL');
        expect(out!.crcon_stream_id).toBe('K-1');
        expect(out!.occurred_at).toBe(TS_ISO);
        expect(out!.payload).toEqual({
            killer: { steam_id_64: '76561198000000001', name: 'Alice' },
            victim: { steam_id_64: '76561198000000002', name: 'Bob' },
            weapon: 'KAR98K',
        });
    });

    it('maps TEAM KILL → player_team_kill', () => {
        const out = normalise(
            entry('TK-1', 'TEAM KILL', {
                steam_id_64_1: '76561198000000001',
                player: 'Alice',
                steam_id_64_2: '76561198000000003',
                player2: 'Carol',
                weapon: 'GRENADE',
            }),
        );
        expect(out).not.toBeNull();
        expect(out!.event_type).toBe('player_team_kill');
        expect(out!.crcon_action).toBe('TEAM KILL');
        expect(out!.payload).toEqual({
            killer: { steam_id_64: '76561198000000001', name: 'Alice' },
            victim: { steam_id_64: '76561198000000003', name: 'Carol' },
            weapon: 'GRENADE',
        });
    });

    it('maps CONNECTED → player_connect with steam_id+name', () => {
        const out = normalise(
            entry('C-1', 'CONNECTED', {
                steam_id_64_1: '76561198000000004',
                player: 'Dave',
            }),
        );
        expect(out).not.toBeNull();
        expect(out!.event_type).toBe('player_connect');
        expect(out!.crcon_action).toBe('CONNECTED');
        expect(out!.payload).toEqual({
            steam_id_64: '76561198000000004',
            name: 'Dave',
        });
    });

    it('maps DISCONNECTED → player_disconnect', () => {
        const out = normalise(
            entry('D-1', 'DISCONNECTED', {
                steam_id_64_1: '76561198000000004',
                player: 'Dave',
            }),
        );
        expect(out).not.toBeNull();
        expect(out!.event_type).toBe('player_disconnect');
        expect(out!.crcon_action).toBe('DISCONNECTED');
        expect(out!.payload).toEqual({
            steam_id_64: '76561198000000004',
            name: 'Dave',
        });
    });

    it('maps TEAMSWITCH → team_switch with from/to', () => {
        const out = normalise(
            entry('TS-1', 'TEAMSWITCH', {
                steam_id_64_1: '76561198000000005',
                player: 'Eve',
                from_team: 'allies',
                to_team: 'axis',
            }),
        );
        expect(out).not.toBeNull();
        expect(out!.event_type).toBe('team_switch');
        expect(out!.crcon_action).toBe('TEAMSWITCH');
        expect(out!.payload).toEqual({
            steam_id_64: '76561198000000005',
            name: 'Eve',
            from_team: 'allies',
            to_team: 'axis',
        });
    });

    it('maps MATCH START → game_start with map/mode', () => {
        const out = normalise(
            entry('M-1', 'MATCH START', {
                map: 'foy_warfare',
                mode: 'warfare',
            }),
        );
        expect(out).not.toBeNull();
        expect(out!.event_type).toBe('game_start');
        expect(out!.crcon_action).toBe('MATCH START');
        expect(out!.payload).toEqual({
            map: 'foy_warfare',
            mode: 'warfare',
        });
    });

    it('maps MATCH ENDED → match_end with winning_team/scores/ended_at', () => {
        const out = normalise(
            entry('M-2', 'MATCH ENDED', {
                winning_team: 'allies',
                allies_score: 5,
                axis_score: 2,
            }),
        );
        expect(out).not.toBeNull();
        expect(out!.event_type).toBe('match_end');
        expect(out!.crcon_action).toBe('MATCH ENDED');
        expect(out!.payload).toEqual({
            winning_team: 'allies',
            allies_score: 5,
            axis_score: 2,
            ended_at: TS_ISO,
        });
    });

    it('returns null for CHAT (unsubscribed) action', () => {
        const out = normalise(
            entry('CH-1', 'CHAT', {
                steam_id_64_1: '76561198000000001',
                player: 'Alice',
                message: 'gg',
            }),
        );
        expect(out).toBeNull();
    });
});
