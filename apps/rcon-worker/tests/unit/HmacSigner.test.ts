// Wave 0 RED stub — replaced by plan 08-10 (real HMAC-SHA256 over `${timestamp}.${body}`
// via node:crypto + timing-safe verify). RESEARCH § HMAC Architecture → Worker signing
// + Pitfall 1 (sign RAW bytes — re-serialising on verification side breaks the signature).
//
// Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
import { describe, expect, it } from 'vitest';

describe('HmacSigner', () => {
    it('produces a stable HMAC-SHA256 over (timestamp + body) and timing-safe verifies', () => {
        expect(true).toBe(false);
    });
});
