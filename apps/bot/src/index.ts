// Trenchwars bot — entrypoint.
//
// Source: .planning/phases/05-discord-bot-v1/05-08-PLAN.md task 1 (Wave 6),
// amended by 05-09-PLAN.md task 2 (Wave 7) to wire the slash command
// dispatcher + register commands at boot, then by 05-11-PLAN.md task 2
// (Wave 10) to register the guildMemberUpdate role-drift reconciler.
//
// Boot order:
//   1. createClient()                              — discord.js Client w/ intents
//   2. registerReadyHandler(client)                — ClientReady -> registerCommands + startOutboundWorker
//   3. registerInteractionHandler(client)          — Events.InteractionCreate
//                                                     dispatcher (chat input + modal
//                                                     + buttons)
//   4. registerGuildMemberUpdateHandler(client)    — Events.GuildMemberUpdate
//                                                     -> POST /discord-events/role-change
//                                                     (Pattern 6 / SC-4 reconciliation
//                                                     second half)
//   5. process error handlers                      — force-exit on uncaught errors
//   6. client.login(env.DISCORD_BOT_TOKEN)         — kicks the gateway handshake

import { createClient } from './client.js';
import { env } from './env.js';
import { registerGuildMemberUpdateHandler } from './events/guildMemberUpdate.js';
import { registerInteractionHandler } from './events/interactionCreate.js';
import { registerReadyHandler } from './events/ready.js';

const client = createClient();

registerReadyHandler(client);
registerInteractionHandler(client);
registerGuildMemberUpdateHandler(client);

process.on('uncaughtException', (err) => {
    console.error('[bot] uncaughtException:', err);
    process.exit(1);
});
process.on('unhandledRejection', (err) => {
    console.error('[bot] unhandledRejection:', err);
    process.exit(1);
});

await client.login(env.DISCORD_BOT_TOKEN);
