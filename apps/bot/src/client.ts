// Trenchwars bot — discord.js Client factory.
//
// Source: .planning/phases/05-discord-bot-v1/05-08-PLAN.md task 1 (Wave 6).
// RESEARCH §Pitfall 6: GuildMembers is a PRIVILEGED intent. It MUST be enabled
// in the Discord Developer Portal (Bot → Privileged Gateway Intents → Server
// Members Intent: ON) for `guildMemberUpdate` events to fire. Without it,
// SC-4 (role sync reconciliation in plan 05-12) silently fails — the operator
// smoke checklist (`05-VALIDATION.md`) verifies portal-side configuration; the
// code declares the intent here so the gateway handshake requests it.
//
// Partials: User, GuildMember, Channel — required when discord.js receives a
// payload that references an object the client hasn't cached yet (e.g., a
// member who joined before bot started). Without these, callbacks receive
// `undefined` instead of a partial object and downstream `.fetch()` patterns
// don't work.

import { Client, GatewayIntentBits, Partials } from 'discord.js';

export function createClient(): Client {
    return new Client({
        intents: [
            GatewayIntentBits.Guilds,
            GatewayIntentBits.GuildMembers, // privileged — Pitfall 6
            GatewayIntentBits.GuildModeration,
        ],
        partials: [Partials.User, Partials.GuildMember, Partials.Channel],
    });
}
