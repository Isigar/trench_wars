// Trenchwars bot — signup modal builder + submit handler.
//
// Source: .planning/phases/05-discord-bot-v1/05-10-PLAN.md task 2 (Wave 9),
// RESEARCH §Pattern 2 (modal-flow) + Pitfall 11 (modal submit fresh 3s).
//
// Two exports:
//
//   buildSignupModal(matchId)
//     - Single role TextInput; customId follows the encodeButtonId scheme
//       ('m:o:<matchId>'). Consumed by:
//         * /match signup slash command (apps/bot/src/commands/match.ts)
//         * RSVP button match_open_signup_modal variant (rsvpButton.ts)
//
//   handle(interaction)
//     - Dispatcher (events/interactionCreate.ts) has ALREADY deferReply'd
//       (Pitfall 11). We editReply with success or translated error.
//     - decodeButtonId(interaction.customId) returns the matchId.
//     - Defensive UUID-shape regex BEFORE hitting the API.
//
// SC-2 wiring: this handler is the end-to-end Discord-side completion of the
// signup flow. The web side's BotApiMatchSignupController (plan 05-04)
// enforces capacity / tag access / idempotency / row-lock; we surface its
// typed errors to user copy via translateError.

import {
    ActionRowBuilder,
    ModalBuilder,
    type ModalSubmitInteraction,
    TextInputBuilder,
    TextInputStyle,
} from 'discord.js';

import { translateError } from './rsvpButton.js';
import { decodeButtonId, encodeButtonId } from '../lib/customIds.js';
import { api } from '../services/api.js';

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

/**
 * buildSignupModal - ModalBuilder for the role-input modal.
 *
 * customId scheme: encodeButtonId({kind: 'match_open_signup_modal', matchId})
 * which produces `m:o:<matchId>`. The submit handler decodes the same scheme
 * for round-trippability (D-05-09-C).
 */
export function buildSignupModal(matchId: string): ModalBuilder {
    return new ModalBuilder()
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
}

export async function handle(interaction: ModalSubmitInteraction): Promise<void> {
    const decoded = decodeButtonId(interaction.customId);
    if (decoded === null || decoded.kind !== 'match_open_signup_modal') {
        await interaction.editReply('Unknown modal.');
        return;
    }

    const roleId = interaction.fields.getTextInputValue('role').trim();

    // T-05-10-05: defensive UUID shape check before POST. The web side uses
    // Eloquent + JSON parameter binding (no SQL injection risk), but the
    // shape check gives a fast, friendly response for typos before the
    // round-trip.
    if (!UUID_RE.test(roleId)) {
        await interaction.editReply('Invalid role UUID.');
        return;
    }

    try {
        await api.post(
            `/matches/${decoded.matchId}/signups`,
            { game_role_id: roleId },
            { actsAsDiscordId: interaction.user.id },
        );
        await interaction.editReply('Signed up to the match.');
    } catch (err) {
        await interaction.editReply(translateError(err));
    }
}
