// Source: .planning/phases/05-discord-bot-v1/05-01-PLAN.md task 2 (Wave 0 RED stub).
// SC-3 — customId encode/decode helpers (round-trip safety). The customId string is
// the only thing Discord echoes back when a user interacts with a button or modal,
// so encoding the (matchId, gameRoleId, action) triple correctly is critical.
// Replaced by plan 05-10 (bot components + embed builders wave).

import { describe, it } from 'vitest';

describe('customId encode/decode', () => {
    it.todo('round-trips match:rsvp:<matchId>:<gameRoleId> — plan 05-10');
    it.todo('rejects malformed customId strings — plan 05-10');
});
