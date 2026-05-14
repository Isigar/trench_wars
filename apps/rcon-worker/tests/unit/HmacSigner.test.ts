// Plan 08-10 task 1 — replaces Wave-0 RED stub from plan 08-01.
// Source: .planning/phases/08-rcon-automation/08-10-PLAN.md task 1 behaviour list.
//
// 7 cases (per the plan):
//   1. sign() with empty secret throws.
//   2. sign() produces deterministic hex for fixed (secret, body, timestamp).
//   3. verify() returns true on round-trip.
//   4. verify() returns false on tampered body.
//   5. verify() returns false on wrong secret.
//   6. verify() returns false on unequal-length sigs (no buffer overrun).
//   7. nonce() produces RFC4122 v4 UUID format.
//
// Cross-tier contract is proven against the Laravel HmacVerifier in plan 08-12 SC-5.
import { createHmac } from 'node:crypto';
import { describe, expect, it } from 'vitest';
import { nonce, sign, verify } from '../../src/ingest/HmacSigner.js';

const SECRET = 'a'.repeat(32); // satisfy config WEB_HMAC_SECRET.min(32) shape
const BODY = '{"events":[{"event_type":"player_kill","crcon_stream_id":"123"}]}';
const TIMESTAMP = '1715670000000';

describe('HmacSigner', () => {
    it('sign() throws when secret is empty', () => {
        expect(() => sign('', BODY, TIMESTAMP)).toThrow(/empty secret/);
    });

    it('sign() produces deterministic hex for fixed inputs (matches Node createHmac directly)', () => {
        const expected = createHmac('sha256', SECRET).update(TIMESTAMP + BODY).digest('hex');
        const actual = sign(SECRET, BODY, TIMESTAMP);
        expect(actual).toBe(expected);
        // Deterministic across calls.
        expect(sign(SECRET, BODY, TIMESTAMP)).toBe(actual);
        // Lowercase hex, 64 chars (HMAC-SHA256 → 32 bytes → 64 hex chars).
        expect(actual).toMatch(/^[0-9a-f]{64}$/);
    });

    it('verify() returns true on round-trip', () => {
        const sig = sign(SECRET, BODY, TIMESTAMP);
        expect(verify(SECRET, BODY, TIMESTAMP, sig)).toBe(true);
    });

    it('verify() returns false on tampered body', () => {
        const sig = sign(SECRET, BODY, TIMESTAMP);
        const tampered = BODY.replace('player_kill', 'player_team_kill');
        expect(verify(SECRET, tampered, TIMESTAMP, sig)).toBe(false);
    });

    it('verify() returns false on wrong secret', () => {
        const sig = sign(SECRET, BODY, TIMESTAMP);
        const wrongSecret = 'b'.repeat(32);
        expect(verify(wrongSecret, BODY, TIMESTAMP, sig)).toBe(false);
    });

    it('verify() returns false on unequal-length signatures (no buffer overrun)', () => {
        // Truncated signature must not crash timingSafeEqual — return false instead.
        const shortSig = 'deadbeef'; // 4 bytes, not 32
        expect(verify(SECRET, BODY, TIMESTAMP, shortSig)).toBe(false);

        // Empty signature also returns false.
        expect(verify(SECRET, BODY, TIMESTAMP, '')).toBe(false);
    });

    it('nonce() produces RFC4122 v4 UUID format', () => {
        const id = nonce();
        // RFC4122 v4: 8-4-4-4-12 hex with version nibble = 4 and variant nibble ∈ {8,9,a,b}.
        expect(id).toMatch(
            /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/,
        );
        // Each call returns a fresh UUID.
        expect(nonce()).not.toBe(id);
    });
});
