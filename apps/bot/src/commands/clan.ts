// Trenchwars bot — /clan slash command.
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 1 (Wave 7).
// RESEARCH §Pattern 2 + Open Question Q2. Ships 3 subcommands:
//
//   /clan info <slug>   — single clan card
//   /clan list          — paginated clan list
//   /clan apply <slug>  — v1: redirect-to-web message (Open Question Q2)
//
// Pitfall 1: every branch calls interaction.deferReply() as the FIRST awaited
// statement to claim the 3s interaction-response window.
//
// /clan apply v1 implementation note: the web-side endpoint
// /api/bot/clans/{id}/applications is documented as "future v1+" in RESEARCH.
// Shipping the slash command stub with a redirect-to-web message satisfies
// SC-1 ("command exists with privacy-aware response") because:
//   1. The command itself is registered in Discord — invocable.
//   2. The "use the website" response is privacy-aware (the website's auth
//      gate enforces clan-application policy, not the bot).
//   3. Discord users without web login are blocked from applying anyway (per
//      RESEARCH Q2 reasoning) — there's no UX regression.
// Phase 6+ ships the live BotApiClanApplicationController endpoint and this
// handler swaps to api.post() at that time.

import {
    ChatInputCommandInteraction,
    MessageFlags,
    SlashCommandBuilder,
} from 'discord.js';

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
        const clans = await api.get<{ data: ClanData[] }>('/clans', {
            actsAsDiscordId: interaction.user.id,
        });
        await interaction.editReply(formatClanList(clans.data));
        return;
    }

    if (sub === 'apply') {
        const slug = interaction.options.getString('slug', true);
        // v1 — redirect to web (Open Question Q2 resolution). Phase 6+ ships
        // the live /api/bot/clans/{slug}/applications endpoint and this
        // branch flips to api.post().
        await interaction.editReply(
            `Apply via the website: visit the clan page for **${slug}** and click "Apply". ` +
                'Discord applications are not yet available — coming in a future release.',
        );
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
