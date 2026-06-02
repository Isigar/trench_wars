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
//   5. process error handlers                      — force-exit on uncaughtException;
//                                                     log-and-survive on
//                                                     unhandledRejection (H7)
//   6. SIGTERM / SIGINT handlers                    — graceful shutdown: stop the
//                                                     outbound poll loop, destroy
//                                                     the gateway client, exit 0 (H6)
//   7. client.login(env.DISCORD_BOT_TOKEN)         — kicks the gateway handshake

import { createClient } from './client.js';
import { env } from './env.js';
import { registerGuildMemberUpdateHandler } from './events/guildMemberUpdate.js';
import { registerInteractionHandler } from './events/interactionCreate.js';
import { registerReadyHandler } from './events/ready.js';
import { stopOutboundWorker } from './services/outbound.js';

const client = createClient();

registerReadyHandler(client);
registerInteractionHandler(client);
registerGuildMemberUpdateHandler(client);

// uncaughtException is genuinely unrecoverable (corrupt process state) — log
// and force-exit so the orchestrator restarts the container.
process.on('uncaughtException', (err) => {
    console.error('[bot] uncaughtException:', err);
    process.exit(1);
});

// H7: a transient unhandledRejection (e.g. a dropped gateway request that
// discord.js auto-reconnects) must NOT hard-exit. Doing so crash-loops the
// container against Railway's restartPolicyMaxRetries:5 and causes a permanent
// outage. Log it (and let discord.js gateway auto-reconnect recover) instead.
process.on('unhandledRejection', (err) => {
    console.error('[bot] unhandledRejection (surviving):', err);
});

// H6: graceful shutdown. Railway / docker compose stop send SIGTERM first; we
// honour SIGINT (Ctrl-C in local dev) too. Stop the outbound poll loop, tear
// down the gateway connection, then exit 0. The shuttingDown flag guards
// against a second signal (or SIGINT after SIGTERM) re-entering teardown.
let shuttingDown = false;
const shutdown = (signal: NodeJS.Signals): void => {
    if (shuttingDown) {
        return;
    }
    shuttingDown = true;
    console.log(`[bot] received ${signal}; shutting down`);
    stopOutboundWorker();
    client
        .destroy()
        .catch((err: unknown) => {
            console.error('[bot] error during client.destroy():', err);
        })
        .finally(() => {
            process.exit(0);
        });
};
process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);

await client.login(env.DISCORD_BOT_TOKEN);
