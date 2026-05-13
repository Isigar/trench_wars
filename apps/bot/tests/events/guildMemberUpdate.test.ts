// Source: .planning/phases/05-discord-bot-v1/05-01-PLAN.md task 2 (Wave 0 RED stub).
// SC-4 — guildMemberUpdate event handler diffs the role cache, POSTs each delta to
// /api/bot/discord-events/role-change; Laravel side reconciles or suppresses as echo.
// Replaced by plan 05-11 (bot outbound worker + guildMemberUpdate wave).

import { describe, it } from 'vitest';

describe('guildMemberUpdate event handler', () => {
    it.todo('diffs role cache and reports added roles — plan 05-11');
    it.todo('diffs role cache and reports removed roles — plan 05-11');
});
