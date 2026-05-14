// Wave 0 RED stub — replaced by plan 08-10 (CrconClient ws + undici fetch + reconnect
// with exponential backoff + jitter + `last_seen_id` resume — RESEARCH Pattern 1).
// Integration scope: stand up a mock CRCON server (websocket-mock or ws.Server), open
// CrconClient, force a reconnect, assert resumeFromLastSeenId() replays missed events.
//
// Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
import { describe, expect, it } from 'vitest';

describe('CrconClient (integration)', () => {
    it('reconnects on socket close + resumes from last_seen_id without dropping events', () => {
        expect(true).toBe(false);
    });
});
