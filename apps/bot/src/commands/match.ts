// Trenchwars bot — /match slash command.
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 1 (Wave 7)
//         + 05-10-PLAN.md task 2 (Wave 9) — embed upgrade.
//
// RESEARCH §Pattern 2 verbatim. Ships 4 subcommands routed off
// `interaction.options.getSubcommand()`:
//
//   /match list             — paginated list of open matches (top 5)
//   /match info <id>        — single match details (EmbedBuilder)
//   /match signup <id>      — OPENS MODAL (no defer; Pitfall 1 corollary)
//   /match leave <id> <role> — releases a signup slot
//
// Pitfall 1: every non-modal branch MUST call interaction.deferReply() as the
// FIRST awaited statement to claim Discord's 3s interaction-response window;
// the API call + editReply complete inside the 15min follow-up window.
//
// Pitfall 1 corollary: the signup branch invokes interaction.showModal() as
// the INITIAL response — discord.js refuses showModal on a deferred / replied
// interaction. The modal builder is shared with rsvpButton's
// match_open_signup_modal variant (apps/bot/src/components/signupModal.ts
// buildSignupModal).
//
// SC-5 attribution: every api.get/delete call sets actsAsDiscordId =
// interaction.user.id so the web side's ResolveBotActsAsUserMiddleware (plan
// 05-04) rebinds Sanctum auth to the invoking Discord user. The privacy gate
// downstream sees the correct viewer.
//
// Plan 05-10 upgrade: plain-text formatMatchList / formatMatchInfo replaced
// by matchCard(m) (apps/bot/src/lib/embeds.ts). For /match list, top-5
// matches emit one matchCard each (Discord caps at 10 embeds per reply;
// 5 is conservative + readable). Empty list still emits the plain string
// 'No open matches.' so the existing match.test.ts assertion stays green.

import {
    ChatInputCommandInteraction,
    MessageFlags,
    SlashCommandBuilder,
} from 'discord.js';

import { buildSignupModal } from '../components/signupModal.js';
import { matchCard } from '../lib/embeds.js';
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
        await interaction.showModal(buildSignupModal(matchId));
        return;
    }

    // Pitfall 1: every other branch defers FIRST to claim the 3s window.
    await interaction.deferReply({ flags: MessageFlags.Ephemeral });

    if (sub === 'list') {
        const matches = await api.get<{ data: PublicMatchData[] }>('/matches', {
            actsAsDiscordId: interaction.user.id,
        });
        if (matches.data.length === 0) {
            await interaction.editReply('No open matches.');
            return;
        }
        // Discord allows up to 10 embeds per reply; top-5 keeps the message
        // readable. Pagination polish deferred to plan 05-12.
        const top = matches.data.slice(0, 5);
        const embeds = top.flatMap((m) => matchCard(m).embeds);
        await interaction.editReply({ embeds });
        return;
    }

    if (sub === 'info') {
        const matchId = interaction.options.getString('id', true);
        const match = await api.get<PublicMatchData>(`/matches/${matchId}`, {
            actsAsDiscordId: interaction.user.id,
        });
        const reply = matchCard(match);
        await interaction.editReply(reply);
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
