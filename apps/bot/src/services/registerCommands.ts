// Trenchwars bot — slash command registration service.
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 1 (Wave 7).
// RESEARCH §Pattern 3 verbatim. Registers all commands declared in
// `../commands/index.ts` against the league's single Discord guild (D-003).
//
// Why guild-scoped (`applicationGuildCommands`) and NOT global
// (`applicationCommands`):
//   - Guild registration propagates INSTANTLY (Discord caches commands per
//     guild; the gateway sees the update in <1s).
//   - Global registration takes up to 1 HOUR to propagate (Discord's CDN
//     cache). For a single-league deployment (D-003) the global registration
//     would have no upside.
//   - If we ever split into multiple guilds (federated leagues), this
//     handler iterates guildIds — out of scope for v1.
//
// Idempotency: rest.put with the full body atomically REPLACES Discord's
// command list for the guild. Re-running on every boot is safe — the only
// state change is when a command is added / removed / renamed in code.
//
// Threat mitigation T-05-09-05 (bot registers to wrong guild): env vars are
// fail-fast at module-load (plan 05-08 env.ts); a wrong DISCORD_GUILD_ID
// would either 404 from Discord's REST API (visible in the operator log)
// or register commands into a guild the bot can't see (visible at smoke
// test in plan 05-13).
//
// Threat mitigation T-05-09-06 (registerCommands fails -> bot keeps
// running): the caller (events/ready.ts) wraps this in try/catch + logs;
// the bot continues to run but slash commands won't be invocable until the
// underlying error (network, auth) is resolved.

import { REST, Routes } from 'discord.js';

import { commands } from '../commands/index.js';
import { env } from '../env.js';

export async function registerCommands(): Promise<void> {
    const rest = new REST({ version: '10' }).setToken(env.DISCORD_BOT_TOKEN);
    const body = Array.from(commands.values()).map((c) => c.data.toJSON());

    await rest.put(
        Routes.applicationGuildCommands(env.DISCORD_APPLICATION_ID, env.DISCORD_GUILD_ID),
        { body },
    );

    console.log(
        `[bot] Registered ${body.length} slash commands to guild ${env.DISCORD_GUILD_ID}`,
    );
}
