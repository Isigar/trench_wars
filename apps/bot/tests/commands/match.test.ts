// Source: .planning/phases/05-discord-bot-v1/05-01-PLAN.md task 2 (Wave 0 RED stub).
// SC-1 / SC-2 — /match list|info|signup|leave subcommand routing inside one
// SlashCommandBuilder; defers reply within 3s, calls ApiClient with
// X-Bot-Acts-As-User header, renders matchCard EmbedBuilder.
// Replaced by plan 05-09 (bot slash commands wave).

import { describe, it } from 'vitest';

describe('/match slash command', () => {
    it.todo('routes "info" subcommand to BotApiMatchController::show — plan 05-09');
    it.todo('routes "signup" subcommand to signup modal — plan 05-09 / 05-10');
    it.todo('routes "leave" subcommand to BotApiMatchSignupController::destroy — plan 05-09');
});
