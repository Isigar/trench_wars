// Trenchwars bot — /me slash command.
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 1 (Wave 7).
// Resolves Open Question Q5:
//
//   /me — own profile lookup (no options, no subcommands).
//
// PlayerPrivacyGate (Phase 2 plan 02-05) implements own-profile bypass:
// when subject_user_id == viewer_user_id, the gate returns the full profile
// (all privacy tiers visible). Plan 05-04's BotApiUserController::me
// confirms this contract — /api/bot/users/me with X-Bot-Acts-As-User set
// to interaction.user.id resolves to subject == viewer == the invoking
// Discord user, triggering the bypass and returning full data.
//
// Pitfall 1: deferReply() is the FIRST awaited statement — claims the 3s
// interaction-response window.
//
// SC-5 attribution: actsAsDiscordId = interaction.user.id forwards to
// X-Bot-Acts-As-User; the web-side ResolveBotActsAsUserMiddleware (plan
// 05-04) rebinds Sanctum auth to the invoking Discord user.

import {
    ChatInputCommandInteraction,
    MessageFlags,
    SlashCommandBuilder,
} from 'discord.js';

import { api } from '../services/api.js';
import type { PlayerData, UserData } from '../types/apiContracts.js';

export const data = new SlashCommandBuilder()
    .setName('me')
    .setDescription('Show your own profile (own-profile bypass — full data)');

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
    await interaction.deferReply({ flags: MessageFlags.Ephemeral });

    const me = await api.get<{ user: UserData; player: PlayerData }>('/users/me', {
        actsAsDiscordId: interaction.user.id,
    });

    // Plan 05-10 will replace this with a profileCard EmbedBuilder honoring
    // the viewer's locale.
    await interaction.editReply(formatMe(me));
}

function formatMe(me: { user: UserData; player: PlayerData }): string {
    return [
        `Discord: ${me.user.username} (${me.user.discord_id})`,
        `Locale: ${me.user.locale}`,
        `Player slug: ${me.player.slug}`,
        `Player id: ${me.player.id}`,
    ].join('\n');
}
