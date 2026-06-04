// Trenchwars bot — /clan slash command.
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 1 (Wave 7).
// RESEARCH §Pattern 2 + Open Question Q2. Ships 3 subcommands:
//
//   /clan info <slug>   — single clan card
//   /clan list          — paginated clan list
//   /clan apply <slug>  — live api.post to /clans/{slug}/applications
//                         (Phase 10-05; replaces the v1 redirect-to-web stub)
//
// Pitfall 1: every branch calls interaction.deferReply() as the FIRST awaited
// statement to claim the 3s interaction-response window.

import {
    ChatInputCommandInteraction,
    MessageFlags,
    SlashCommandBuilder,
} from 'discord.js';

import { translateError } from '../components/rsvpButton.js';
import { paginationButtons } from '../lib/buttons.js';
import { api } from '../services/api.js';
import type { ClanData, ListMeta } from '../types/apiContracts.js';

export const data = new SlashCommandBuilder()
    .setName('clan')
    .setDescription('Inspect, list, or apply to clans')
    .addSubcommand((sc) =>
        sc
            .setName('info')
            .setDescription('Show clan details')
            .addStringOption((o) =>
                o.setName('slug').setDescription('Clan slug').setRequired(true),
            ),
    )
    .addSubcommand((sc) => sc.setName('list').setDescription('List clans'))
    .addSubcommand((sc) =>
        sc
            .setName('apply')
            .setDescription('Apply to join a clan')
            .addStringOption((o) =>
                o.setName('slug').setDescription('Clan slug').setRequired(true),
            ),
    );

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
    await interaction.deferReply({ flags: MessageFlags.Ephemeral });

    const sub = interaction.options.getSubcommand(true);

    if (sub === 'info') {
        const slug = interaction.options.getString('slug', true);
        // /clans/{slug} returns a { data } envelope (BotApiClanController::show);
        // api.get() does not unwrap, so destructure .data — reading it bare
        // yields "Clan undefined [undefined]".
        const { data: clan } = await api.get<{ data: ClanData }>(`/clans/${slug}`, {
            actsAsDiscordId: interaction.user.id,
        });
        // Plan 05-10 will replace this with a clanCard EmbedBuilder.
        await interaction.editReply(formatClanInfo(clan));
        return;
    }

    if (sub === 'list') {
        return await renderClanListPage(interaction, 1);
    }

    if (sub === 'apply') {
        const slug = interaction.options.getString('slug', true);
        try {
            await api.post(
                `/clans/${slug}/applications`,
                {},
                { actsAsDiscordId: interaction.user.id },
            );
            await interaction.editReply('Your application has been submitted.');
        } catch (err) {
            await interaction.editReply(translateError(err));
        }
        return;
    }
}

// Plain-text formatters — replaced by embed builders in plan 05-10.
function formatClanInfo(c: ClanData): string {
    return [
        `Clan ${c.name} [${c.tag}]`,
        `Slug: ${c.slug}`,
        `Status: ${c.status}`,
        `Active members: ${c.active_member_count}`,
    ].join('\n');
}

function formatClanList(clans: ClanData[]): string {
    if (clans.length === 0) return 'No clans.';
    return clans.map((c) => `- [${c.tag}] ${c.name} (${c.slug})`).join('\n');
}

/**
 * renderClanListPage — fetches and renders a page of clans.
 *
 * Exported so plan 12-04's button handler can call the same builder without
 * duplicating fetch/render logic.
 *
 * - Fetches /clans?page=N with the invoking user's Discord ID forwarded.
 * - Empty data → plain-string reply; no components.
 * - last_page === 1 → plain-text content only; no pagination components.
 * - last_page > 1  → plain-text content + Prev/Next ActionRow + "Page X of Y".
 */
export async function renderClanListPage(
    interaction: ChatInputCommandInteraction,
    page: number,
): Promise<void> {
    const result = await api.get<{ data: ClanData[]; meta: ListMeta }>(
        `/clans?page=${page}`,
        { actsAsDiscordId: interaction.user.id },
    );
    const { data: clans, meta } = result;

    const listText = formatClanList(clans);

    if (clans.length === 0) {
        await interaction.editReply(listText);
        return;
    }

    if (meta.last_page <= 1) {
        await interaction.editReply(listText);
        return;
    }

    // Multi-page: include Prev/Next row and a page indicator.
    const components = [paginationButtons('clan', meta.current_page, meta.last_page)];
    const content = `${listText}\nPage ${meta.current_page} of ${meta.last_page}`;
    await interaction.editReply({ content, components });
}
