// Source: .planning/phases/05-discord-bot-v1/05-01-PLAN.md task 2 (Wave 0 RED stub).
// SC-1 — /clan info|list|apply subcommand routing; renders clanCard EmbedBuilder
// for info, paginated list for list, calls /api/bot/clans/{id}/applications for apply.
// Replaced by plan 05-09 (bot slash commands wave).

import { describe, it } from 'vitest';

describe('/clan slash command', () => {
    it.todo('routes "list" to BotApiClanController::index — plan 05-09');
    it.todo('routes "info" to BotApiClanController::showByDiscordRole — plan 05-09');
    it.todo('routes "apply" to BotApiClanApplicationController::store — plan 05-09');
});
