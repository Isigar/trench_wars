// Trenchwars bot — entrypoint.
//
// Source: .planning/phases/05-discord-bot-v1/05-08-PLAN.md task 1 (Wave 6),
// amended by 05-09-PLAN.md task 2 (Wave 7) to wire the slash command
// dispatcher + register commands at boot. Plan 05-11 will inject the
// outbound worker startup inside the ready handler.
//
// Boot order:
//   1. createClient()                      — discord.js Client w/ intents
//   2. registerReadyHandler(client)        — ClientReady -> registerCommands
//   3. registerInteractionHandler(client)  — Events.InteractionCreate
//                                             dispatcher (chat input + modal
//                                             + buttons)
//   4. process error handlers              — force-exit on uncaught errors
//   5. client.login(env.DISCORD_BOT_TOKEN) — kicks the gateway handshake

import { createClient } from './client.js';
import { env } from './env.js';
import { registerInteractionHandler } from './events/interactionCreate.js';
import { registerReadyHandler } from './events/ready.js';

const client = createClient();

registerReadyHandler(client);
registerInteractionHandler(client);

process.on('uncaughtException', (err) => {
    console.error('[bot] uncaughtException:', err);
    process.exit(1);
});
process.on('unhandledRejection', (err) => {
    console.error('[bot] unhandledRejection:', err);
    process.exit(1);
});

await client.login(env.DISCORD_BOT_TOKEN);
