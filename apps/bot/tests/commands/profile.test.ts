// Source: .planning/phases/05-discord-bot-v1/05-01-PLAN.md task 2 (Wave 0 RED stub).
// SC-1 — /profile [discord_user]? returns privacy-aware profile via
// /api/bot/users/me with X-Bot-Acts-As-User header; renders profileCard embed.
// Replaced by plan 05-09 (bot slash commands wave).

import { describe, it } from 'vitest';

describe('/profile slash command', () => {
    it.todo('renders profileCard for invoker when no target provided — plan 05-09');
    it.todo('respects PlayerPrivacyGate when target is another Discord user — plan 05-09');
});
