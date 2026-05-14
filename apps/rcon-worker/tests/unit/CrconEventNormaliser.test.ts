// Wave 0 RED stub — replaced by plan 08-10 (CrconEventNormaliser.normalise() maps
// raw CRCON log entries to canonical match_event_type tags). Asserts that each
// AllLogTypes enum value (RESEARCH § Log Action Types) maps to ONE of the canonical
// event_type values declared in apps/web/lang/en/rcon.php events.types.* + returns
// null for entries that should be dropped.
//
// Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
import { describe, expect, it } from 'vitest';

describe('CrconEventNormaliser', () => {
    it('maps every AllLogTypes value to a canonical match_event_type or null', () => {
        expect(true).toBe(false);
    });
});
