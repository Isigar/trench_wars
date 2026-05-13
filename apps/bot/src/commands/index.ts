// Trenchwars bot — slash command registry.
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 1 (Wave 7).
// Canonical discord.js Guide pattern: a Map keyed by command name -> module
// exposing `{data, execute}`. The dispatcher in events/interactionCreate.ts
// looks up `commands.get(interaction.commandName)?.execute(interaction)`.
//
// New slash commands land here by:
//   1. import * as foo from './foo'
//   2. commands.set(foo.data.name, foo)
// No other wiring required — registerCommands.ts walks `commands.values()`
// at boot to push the registration to Discord (Pattern 3).

import type { ChatInputCommandInteraction, SlashCommandOptionsOnlyBuilder, SlashCommandSubcommandsOnlyBuilder } from 'discord.js';

import * as clan from './clan.js';
import * as match from './match.js';
import * as me from './me.js';
import * as profile from './profile.js';

export interface CommandModule {
    data: SlashCommandOptionsOnlyBuilder | SlashCommandSubcommandsOnlyBuilder | { name: string; toJSON: () => unknown };
    execute: (interaction: ChatInputCommandInteraction) => Promise<void>;
}

export const commands = new Map<string, CommandModule>();
commands.set(clan.data.name, clan as CommandModule);
commands.set(match.data.name, match as CommandModule);
commands.set(me.data.name, me as CommandModule);
commands.set(profile.data.name, profile as CommandModule);
