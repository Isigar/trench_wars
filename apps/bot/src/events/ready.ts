// Trenchwars bot — ClientReady event handler.
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 1 (Wave 7).
// Centralises the boot-time work that depends on the gateway being live:
//   1. Log the bot tag (operator confirmation Discord auth worked).
//   2. Register slash commands to the league's guild (Pattern 3).
//   3. (plan 05-11) startOutboundWorker() will hook into this same handler.
//
// `client.once` (not `client.on`) — ClientReady fires once per gateway
// session; subsequent reconnects raise the (deprecated) `Events.Resume`
// event, which we explicitly ignore (commands stay registered across
// reconnects).
//
// Threat mitigation T-05-09-06 (registerCommands fails -> bot keeps
// running): wrap in try/catch. The operator sees the stack in the
// container log and can either fix the cause + redeploy or manually
// re-register via the Discord dev portal. The bot does NOT crash on this
// failure — gateway connection + outbound worker stay alive.

import { type Client, Events } from 'discord.js';

import { registerCommands } from '../services/registerCommands.js';

export function registerReadyHandler(client: Client): void {
    client.once(Events.ClientReady, (c) => {
        console.log(`[bot] Logged in as ${c.user.tag}`);
        // Fire-and-log; do not block the ready handler chain on failure.
        registerCommands().catch((err: unknown) => {
            console.error('[bot] Failed to register slash commands:', err);
        });
        // plan 05-11 will inject startOutboundWorker() here.
    });
}
