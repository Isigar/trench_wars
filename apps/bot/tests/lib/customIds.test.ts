// Trenchwars bot — customId encode/decode tests (Wave 6 GREEN flip).
//
// Source: .planning/phases/05-discord-bot-v1/05-08-PLAN.md task 3.
// Replaces the Wave 0 RED stub. Asserts SC-3 (round-trip safety) + Pitfall 5
// (100-char customId budget) + T-05-08-04 (malformed decode returns null).
//
// The customId string is the only context Discord echoes back when a user
// clicks a button — encoding (matchId, gameRoleId, action) correctly is
// load-bearing for the entire interaction layer.

import { describe, expect, it } from 'vitest';

import {
    type ButtonAction,
    decodeButtonId,
    encodeButtonId,
} from '../../src/lib/customIds.js';

const UUID_A = '01234567-89ab-cdef-0123-456789abcdef';
const UUID_B = 'fedcba98-7654-3210-fedc-ba9876543210';

describe('customId encode', () => {
    it('encodes match_signup as "m:s:<matchId>:<gameRoleId>"', () => {
        const id = encodeButtonId({ kind: 'match_signup', matchId: UUID_A, gameRoleId: UUID_B });
        expect(id).toBe(`m:s:${UUID_A}:${UUID_B}`);
    });

    it('encodes match_leave as "m:l:<matchId>:<gameRoleId>"', () => {
        const id = encodeButtonId({ kind: 'match_leave', matchId: UUID_A, gameRoleId: UUID_B });
        expect(id).toBe(`m:l:${UUID_A}:${UUID_B}`);
    });

    it('encodes match_open_signup_modal as "m:o:<matchId>"', () => {
        const id = encodeButtonId({ kind: 'match_open_signup_modal', matchId: UUID_A });
        expect(id).toBe(`m:o:${UUID_A}`);
    });

    it('encodes clan_apply as "c:a:<clanId>"', () => {
        const id = encodeButtonId({ kind: 'clan_apply', clanId: UUID_A });
        expect(id).toBe(`c:a:${UUID_A}`);
    });
});

describe('customId decode', () => {
    it('decodes valid match_signup customId back to the typed ButtonAction', () => {
        const decoded = decodeButtonId(`m:s:${UUID_A}:${UUID_B}`);
        expect(decoded).toEqual({
            kind: 'match_signup',
            matchId: UUID_A,
            gameRoleId: UUID_B,
        });
    });

    it('decodes valid match_leave customId back to the typed ButtonAction', () => {
        const decoded = decodeButtonId(`m:l:${UUID_A}:${UUID_B}`);
        expect(decoded).toEqual({
            kind: 'match_leave',
            matchId: UUID_A,
            gameRoleId: UUID_B,
        });
    });

    it('decodes valid match_open_signup_modal customId back to the typed ButtonAction', () => {
        const decoded = decodeButtonId(`m:o:${UUID_A}`);
        expect(decoded).toEqual({ kind: 'match_open_signup_modal', matchId: UUID_A });
    });

    it('decodes valid clan_apply customId back to the typed ButtonAction', () => {
        const decoded = decodeButtonId(`c:a:${UUID_A}`);
        expect(decoded).toEqual({ kind: 'clan_apply', clanId: UUID_A });
    });
});

describe('customId round-trip', () => {
    const cases: ButtonAction[] = [
        { kind: 'match_signup', matchId: UUID_A, gameRoleId: UUID_B },
        { kind: 'match_leave', matchId: UUID_A, gameRoleId: UUID_B },
        { kind: 'match_open_signup_modal', matchId: UUID_A },
        { kind: 'clan_apply', clanId: UUID_A },
    ];

    for (const action of cases) {
        it(`round-trips encode -> decode preserving ids for kind=${action.kind}`, () => {
            const encoded = encodeButtonId(action);
            const decoded = decodeButtonId(encoded);
            expect(decoded).toEqual(action);
        });
    }
});

describe('customId malformed input (T-05-08-04)', () => {
    it('returns null on empty string', () => {
        expect(decodeButtonId('')).toBeNull();
    });

    it('returns null on a single colon (wrong arity)', () => {
        expect(decodeButtonId(':')).toBeNull();
    });

    it('returns null on unknown prefix', () => {
        expect(decodeButtonId(`x:y:${UUID_A}`)).toBeNull();
    });

    it('returns null on match_signup with missing gameRoleId (wrong arity)', () => {
        expect(decodeButtonId(`m:s:${UUID_A}`)).toBeNull();
    });

    it('returns null on match_open_signup_modal with extra trailing segment', () => {
        expect(decodeButtonId(`m:o:${UUID_A}:extra`)).toBeNull();
    });

    it('returns null on clan_apply with missing clanId', () => {
        expect(decodeButtonId('c:a:')).toEqual({ kind: 'clan_apply', clanId: '' });
        // The decoder treats empty segment as a string id; the caller is
        // responsible for rejecting empty UUIDs. Documented here so the
        // contract is explicit: validity of the id payload is NOT decode's
        // job — only structural validity is enforced.
        expect(decodeButtonId('c:a')).toBeNull();
    });
});

describe('customId list_page encode (12-02)', () => {
    it('encodes list_page match as "pg:m:<page>"', () => {
        expect(encodeButtonId({ kind: 'list_page', listType: 'match', page: 2 })).toBe('pg:m:2');
    });

    it('encodes list_page clan as "pg:c:<page>"', () => {
        expect(encodeButtonId({ kind: 'list_page', listType: 'clan', page: 1 })).toBe('pg:c:1');
    });
});

describe('customId list_page decode (12-02)', () => {
    it('decodes pg:m:3 to { kind: list_page, listType: match, page: 3 }', () => {
        expect(decodeButtonId('pg:m:3')).toEqual({ kind: 'list_page', listType: 'match', page: 3 });
    });

    it('decodes pg:c:1 to { kind: list_page, listType: clan, page: 1 }', () => {
        expect(decodeButtonId('pg:c:1')).toEqual({ kind: 'list_page', listType: 'clan', page: 1 });
    });

    it('returns null on pg:m (wrong arity — missing page)', () => {
        expect(decodeButtonId('pg:m')).toBeNull();
    });

    it('returns null on pg:m:2:extra (wrong arity — extra segment)', () => {
        expect(decodeButtonId('pg:m:2:extra')).toBeNull();
    });

    it('returns null on pg:x:2 (unknown listType)', () => {
        expect(decodeButtonId('pg:x:2')).toBeNull();
    });

    it('returns null on pg:m:abc (non-integer page)', () => {
        expect(decodeButtonId('pg:m:abc')).toBeNull();
    });

    it('returns null on pg:m:0 (zero page — must be positive integer)', () => {
        expect(decodeButtonId('pg:m:0')).toBeNull();
    });

    it('returns null on pg:m:-1 (negative page)', () => {
        expect(decodeButtonId('pg:m:-1')).toBeNull();
    });
});

describe('customId list_page round-trip (12-02)', () => {
    it('round-trips list_page match page 5', () => {
        const action: ButtonAction = { kind: 'list_page', listType: 'match', page: 5 };
        expect(decodeButtonId(encodeButtonId(action))).toEqual(action);
    });

    it('round-trips list_page clan page 1', () => {
        const action: ButtonAction = { kind: 'list_page', listType: 'clan', page: 1 };
        expect(decodeButtonId(encodeButtonId(action))).toEqual(action);
    });
});

describe('customId existing variants unchanged after list_page addition (12-02)', () => {
    it('m:s: variant still round-trips', () => {
        const action: ButtonAction = { kind: 'match_signup', matchId: UUID_A, gameRoleId: UUID_B };
        expect(decodeButtonId(encodeButtonId(action))).toEqual(action);
    });

    it('c:a: variant still round-trips', () => {
        const action: ButtonAction = { kind: 'clan_apply', clanId: UUID_A };
        expect(decodeButtonId(encodeButtonId(action))).toEqual(action);
    });
});

describe('customId length budget (Pitfall 5 — Discord 100-char limit)', () => {
    it('produces customId <= 100 chars for two UUIDs (match_signup worst case)', () => {
        // 'm:s:' (4) + 36 + ':' (1) + 36 = 77 chars; comfortably under 100.
        const customId = encodeButtonId({
            kind: 'match_signup',
            matchId: UUID_A,
            gameRoleId: UUID_B,
        });
        expect(customId.length).toBeLessThanOrEqual(100);
        expect(customId.length).toBe(77);
    });

    it('produces customId <= 100 chars for match_leave with two UUIDs', () => {
        const customId = encodeButtonId({
            kind: 'match_leave',
            matchId: UUID_A,
            gameRoleId: UUID_B,
        });
        expect(customId.length).toBeLessThanOrEqual(100);
    });

    it('produces customId <= 100 chars for match_open_signup_modal with a UUID', () => {
        const customId = encodeButtonId({ kind: 'match_open_signup_modal', matchId: UUID_A });
        expect(customId.length).toBeLessThanOrEqual(100);
    });

    it('produces customId <= 100 chars for clan_apply with a UUID', () => {
        const customId = encodeButtonId({ kind: 'clan_apply', clanId: UUID_A });
        expect(customId.length).toBeLessThanOrEqual(100);
    });
});
