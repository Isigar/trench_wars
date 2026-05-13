// Trenchwars bot — ActionRow / ButtonBuilder factories.
//
// Source: .planning/phases/05-discord-bot-v1/05-10-PLAN.md task 1 (Wave 9).
// Three exported helpers cover the three button variants encoded by
// `customIds.ts` (plan 05-08):
//
//   openSignupModalButton(matchId)
//     - rendered by matchCard() when status='open'
//     - clicking it POPS the role-input modal (signupModal.ts handler)
//
//   signupRoleButton(matchId, gameRoleId, label)
//     - reserved for direct-button flows (admin-curated match cards with
//       per-role buttons, future polish only)
//     - matchCard does NOT emit these in v1 (15 HLL roles exceed Discord's
//       25-button-per-message cap once status indicators + leave buttons
//       are factored in)
//
//   leaveRoleButton(matchId, gameRoleId)
//     - paired with signupRoleButton for the direct-button flow
//
// Pitfall 5 mitigation: customId budget is 100 chars per component. The
// encodeButtonId scheme (`m:s:<uuid>:<uuid>` worst case 77 chars) keeps two
// UUIDs comfortably under the budget. setLabel().slice(0, 80) guards the
// label-length cap (Discord ButtonBuilder validates).

import { ActionRowBuilder, ButtonBuilder, ButtonStyle } from 'discord.js';

import { encodeButtonId } from './customIds.js';

// Discord ButtonBuilder.setLabel() max 80 chars.
const LABEL_MAX = 80;

export function openSignupModalButton(matchId: string): ButtonBuilder {
    return new ButtonBuilder()
        .setCustomId(encodeButtonId({ kind: 'match_open_signup_modal', matchId }))
        .setLabel('Sign up')
        .setStyle(ButtonStyle.Success);
}

export function signupRoleButton(
    matchId: string,
    gameRoleId: string,
    label: string,
): ButtonBuilder {
    return new ButtonBuilder()
        .setCustomId(encodeButtonId({ kind: 'match_signup', matchId, gameRoleId }))
        .setLabel(label.slice(0, LABEL_MAX))
        .setStyle(ButtonStyle.Primary);
}

export function leaveRoleButton(
    matchId: string,
    gameRoleId: string,
): ButtonBuilder {
    return new ButtonBuilder()
        .setCustomId(encodeButtonId({ kind: 'match_leave', matchId, gameRoleId }))
        .setLabel('Leave')
        .setStyle(ButtonStyle.Secondary);
}

/**
 * rsvpButtons - convenience helper returning the [Sign up, Leave] pair as a
 * pre-assembled ActionRow.
 *
 * Reserved for future direct-button flows; matchCard() uses
 * openSignupModalButton() alone in v1.
 */
export function rsvpButtons(
    matchId: string,
    gameRoleId: string,
    signupLabel: string,
): ActionRowBuilder<ButtonBuilder> {
    return new ActionRowBuilder<ButtonBuilder>().addComponents(
        signupRoleButton(matchId, gameRoleId, signupLabel),
        leaveRoleButton(matchId, gameRoleId),
    );
}
