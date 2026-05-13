// Trenchwars bot — entrypoint.
//
// Source: .planning/phases/05-discord-bot-v1/05-08-PLAN.md task 1 (Wave 6).
// Plan 05-09 (slash commands) and plan 05-11 (outbound worker) will inject
// their wiring into the Events.ClientReady handler. This file ships the boot
// substrate: env validation (via the env module import), Client construction,
// login, and global process error handlers that force exit so the container
// orchestrator (docker-compose / Railway) restarts the bot rather than leaving
// it in a half-broken state.

import { Events } from 'discord.js';

import { createClient } from './client.js';
import { env } from './env.js';

const client = createClient();

client.once(Events.ClientReady, (c) => {
    console.log(
        `[bot] Logged in as ${c.user.tag} (intents: Guilds, GuildMembers, GuildModeration)`,
    );
    console.log(
        '[bot] Ready. Slash commands + outbound worker will be wired in plan 05-09 + 05-11.',
    );
});

process.on('uncaughtException', (err) => {
    console.error('[bot] uncaughtException:', err);
    process.exit(1);
});
process.on('unhandledRejection', (err) => {
    console.error('[bot] unhandledRejection:', err);
    process.exit(1);
});

await client.login(env.DISCORD_BOT_TOKEN);
