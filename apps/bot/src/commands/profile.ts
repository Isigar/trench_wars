// Trenchwars bot — /profile slash command.
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 1 (Wave 7).
// Open Question Q5 resolution per <interfaces> block:
//
//   /profile <@user>  — looks up another Discord user's public profile.
//
// v1 implementation: redirect-to-web stub.
//
// Rationale (verbatim from <interfaces>):
//
//   The web-side /api/bot/users/me endpoint rebinds Sanctum auth via
//   X-Bot-Acts-As-User; if we send actsAsDiscordId = targetUser.id, the
//   PlayerPrivacyGate sees subject == viewer (own-profile bypass) and
//   returns the FULL profile — leaking private fields. WRONG.
//
//   The correct fix is a new viewer-aware endpoint
//   /api/bot/users/by-discord/{id} that takes the viewer's Discord id in
//   X-Bot-Acts-As-User and the SUBJECT's id in the URL. PlayerPrivacyGate
//   then sees subject != viewer and applies the correct privacy tier.
//
//   That endpoint is NOT in plan 05-04's route list — it's deferred to
//   plan 05-12 (Phase 9 polish). For v1, we ship /profile as a stub that
//   redirects the user to the website. SC-1 is satisfied:
//     1. The command is registered in Discord — invocable.
//     2. The "use the website" response is privacy-aware (the website's
//        PlayerPrivacyGate enforces the tier visibility).
//
// Pitfall 1: deferReply() is the FIRST awaited statement — claims the 3s
// interaction-response window even though we don't make an HTTP call.

import {
    ChatInputCommandInteraction,
    MessageFlags,
    SlashCommandBuilder,
} from 'discord.js';

export const data = new SlashCommandBuilder()
    .setName('profile')
    .setDescription("Show another user's public profile (privacy-aware)")
    .addUserOption((o) =>
        o.setName('user').setDescription('Target Discord user').setRequired(true),
    );

export async function execute(interaction: ChatInputCommandInteraction): Promise<void> {
    await interaction.deferReply({ flags: MessageFlags.Ephemeral });

    const targetUser = interaction.options.getUser('user', true);

    // v1 — redirect to web. The website's PlayerPrivacyGate (Phase 2 plan
    // 02-05) enforces tier-aware field visibility based on the viewer's
    // identity, which we can't safely forward through the current bot API
    // surface (Open Question Q5 — plan 05-12 will wire the viewer-aware
    // /api/bot/users/by-discord/{id} endpoint).
    await interaction.editReply(
        `Visit the website to view <@${targetUser.id}>'s public profile (privacy-aware). ` +
            'Discord-side profile lookup is coming in a future release.',
    );
}
