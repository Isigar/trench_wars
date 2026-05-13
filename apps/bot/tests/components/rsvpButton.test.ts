// Source: .planning/phases/05-discord-bot-v1/05-01-PLAN.md task 2 (Wave 0 RED stub).
// SC-3 — RSVP button handler: customId pattern "match:rsvp:<match_id>:<game_role_id>";
// click → defer reply ephemeral → call BotApiMatchSignupController::store → embed update.
// Replaced by plan 05-10 (bot components wave).

import { describe, it } from 'vitest';

describe('RSVP button handler', () => {
    it.todo('decodes customId pattern and dispatches signup call — plan 05-10');
    it.todo('handles capacity_full error from API with ephemeral reply — plan 05-10');
});
