// Source: .planning/phases/05-discord-bot-v1/05-01-PLAN.md task 2 (Wave 0 RED stub).
// SC-3 — outbound worker polls /api/bot/outbound-messages?status=pending every ~5s,
// for each row renders embed/buttons + channel.send(); on success POSTs /sent with
// sent_message_id; on failure POSTs /failed with last_error.
// Replaced by plan 05-11 (bot outbound worker + guildMemberUpdate wave).

import { describe, it } from 'vitest';

describe('outbound delivery worker', () => {
    it.todo('polls pending rows and dispatches them to Discord channel — plan 05-11');
    it.todo('marks row sent on success with sent_message_id — plan 05-11');
    it.todo('marks row failed on Discord 5xx with last_error — plan 05-11');
});
