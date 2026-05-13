// Trenchwars bot — /match slash command.
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 1 (Wave 7).
// RESEARCH §Pattern 2 verbatim. Ships 4 subcommands routed off
// `interaction.options.getSubcommand()`:
//
//   /match list             — paginated list of open matches
//   /match info <id>        — single match details
//   /match signup <id>      — OPENS MODAL (no defer; Pitfall 1 corollary)
//   /match leave <id> <role> — releases a signup slot
//
// Pitfall 1: every non-modal branch MUST call interaction.deferReply() as the
// FIRST awaited statement to claim Discord's 3s interaction-response window;
// the API call + editReply complete inside the 15min follow-up window.
//
// Pitfall 1 corollary: the signup branch invokes interaction.showModal() as
// the INITIAL response — discord.js refuses showModal on a deferred / replied
// interaction. The modal submit handler ships in plan 05-10.
//
// SC-5 attribution: every api.get/delete call sets actsAsDiscordId =
// interaction.user.id so the web side's ResolveBotActsAsUserMiddleware (plan
// 05-04) rebinds Sanctum auth to the invoking Discord user. The privacy gate
// downstream sees the correct viewer.
//
// Plan 05-10 will replace the plain-text formatters here with EmbedBuilder
// renders (matchListEmbed, matchInfoEmbed) — SC-1 is satisfied at plain-text
// level by this plan; the embed upgrade is a UX polish, not a correctness fix.

import {
    ActionRowBuilder,
    ChatInputCommandInteraction,
    MessageFlags,
    ModalBuilder,
    SlashCommandBuilder,
    TextInputBuilder,
    TextInputStyle,
} from 'discord.js';

import { encodeButtonId } from '../lib/customIds.js';
import { api } from '../services/api.js';
import type { PublicMatchData } from '../types/apiContracts.js';

export const data = new SlashCommandBuilder()
    .setName('match')
    .setDescription('List, inspect, sign up to, or leave matches')
    .addSubcommand((sc) =>
        sc.setName('list').setDescription('List open matches'),
    )
    .addSubcommand((sc) =>
        sc
            .setName('info')
            .setDescription('Show match details')
            .addStringOption((o) =>
                o.setName('id').setDescription('Match UUID').setRequired(true),
            ),
    )
    .addSubcommand((sc) =>
        sc
            .setName('signup')
            .setDescription('Sign up to a match (opens a modal for role selection)')
            .addStringOption((o) =>
                o.setName('id').setDescription('Match UUID').setRequired(true),
            ),
    )
    .addSubcommand((sc) =>
        sc
            .setName('leave')
            .setDescription('Leave a match slot you previously signed up to')
            .addStringOption((o) =>
                o.setName('id').setDescription('Match UUID').setRequired(true),
            )
            .addStringOption((o) =>
                o
                    .setName('role')
                    .setDescription('Game role UUID (see /match info)')
                    .setRequired(true),
            ),
    );

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
    const sub = interaction.options.getSubcommand(true);

    // signup is the ONE subcommand that does NOT defer — modals MUST be the
    // initial response (Pitfall 1 corollary; discord.js refuses showModal on
    // a deferred / replied interaction).
    if (sub === 'signup') {
        const matchId = interaction.options.getString('id', true);
        const modal = new ModalBuilder()
            .setCustomId(encodeButtonId({ kind: 'match_open_signup_modal', matchId }))
            .setTitle('Sign up to match')
            .addComponents(
                new ActionRowBuilder<TextInputBuilder>().addComponents(
                    new TextInputBuilder()
                        .setCustomId('role')
                        .setLabel('Role UUID (from /match info)')
                        .setStyle(TextInputStyle.Short)
                        .setRequired(true),
                ),
            );
        await interaction.showModal(modal);
        return;
    }

    // Pitfall 1: every other branch defers FIRST to claim the 3s window.
    await interaction.deferReply({ flags: MessageFlags.Ephemeral });

    if (sub === 'list') {
        const matches = await api.get<{ data: PublicMatchData[] }>('/matches', {
            actsAsDiscordId: interaction.user.id,
        });
        // Plan 05-10 will replace this plain-text reply with a matchListEmbed
        // EmbedBuilder. SC-1 is satisfied at plain-text level here.
        await interaction.editReply(formatMatchList(matches.data));
        return;
    }

    if (sub === 'info') {
        const matchId = interaction.options.getString('id', true);
        const match = await api.get<PublicMatchData>(`/matches/${matchId}`, {
            actsAsDiscordId: interaction.user.id,
        });
        await interaction.editReply(formatMatchInfo(match));
        return;
    }

    if (sub === 'leave') {
        const matchId = interaction.options.getString('id', true);
        const roleId = interaction.options.getString('role', true);
        try {
            await api.delete(`/matches/${matchId}/signups/${roleId}`, {
                actsAsDiscordId: interaction.user.id,
            });
            await interaction.editReply('You have left the match.');
        } catch (err) {
            // err.message is already scrubbed by services/api.ts (Pitfall 3);
            // slice to keep the editReply inside Discord's 2000-char message cap.
            await interaction.editReply(
                `Failed to leave: ${(err as Error).message.slice(0, 200)}`,
            );
        }
        return;
    }
}

// Plain-text formatters — replaced by embed builders in plan 05-10.
function formatMatchList(matches: PublicMatchData[]): string {
    if (matches.length === 0) return 'No open matches.';
    return matches
        .map((m) => `- ${m.id} | ${m.status} | ${m.scheduled_at ?? '—'}`)
        .join('\n');
}

function formatMatchInfo(m: PublicMatchData): string {
    return [
        `Match ${m.id}`,
        `Status: ${m.status}`,
        `Scheduled: ${m.scheduled_at ?? '—'}`,
        `Host clan id: ${m.host_clan_id ?? '—'}`,
    ].join('\n');
}
