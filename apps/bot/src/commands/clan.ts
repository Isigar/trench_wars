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
import { buildClanListPage } from '../lib/pageBuilders.js';
import { api } from '../services/api.js';
import type { ClanData } from '../types/apiContracts.js';

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

/**
 * renderClanListPage — fetches and renders a page of clans.
 *
 * Delegates to buildClanListPage (lib/pageBuilders.ts) which enforces:
 *   BL-02: out-of-range page clamp to last_page (stale button recovery).
 *   WR-01: content string sliced to 2000 chars (Discord message cap).
 */
export async function renderClanListPage(
    interaction: ChatInputCommandInteraction,
    page: number,
): Promise<void> {
    const payload = await buildClanListPage(page, interaction.user.id);
    if ('content' in payload && payload.embeds.length === 0 && payload.components.length === 0) {
        await interaction.editReply(payload.content as string);
        return;
    }
    await interaction.editReply(payload);
}
