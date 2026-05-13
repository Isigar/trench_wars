// Trenchwars bot — component (button + modal-submit + select-menu) dispatcher.
//
// Source: .planning/phases/05-discord-bot-v1/05-10-PLAN.md task 2 (Wave 9).
// Thin router invoked by events/interactionCreate.ts. v1 has a single
// button-handler namespace (rsvpButton) and a single modal-submit namespace
// (signupModal); future component types add a per-prefix branch here.

import type { ButtonInteraction, ModalSubmitInteraction } from 'discord.js';

import * as rsvpButton from './rsvpButton.js';
import * as signupModal from './signupModal.js';

/**
 * handleButton - routes button interactions to rsvpButton.handle.
 *
 * v1 has a single button namespace (match/clan); rsvpButton.ts performs the
 * fine-grained dispatch via decodeButtonId. The dispatcher
 * (events/interactionCreate.ts) is responsible for the pre-defer decision
 * (modal-opening customIds skip defer; everything else is pre-deferred per
 * Pitfall 11).
 */
export async function handleButton(interaction: ButtonInteraction): Promise<void> {
    await rsvpButton.handle(interaction);
}

/**
 * handleModalSubmit - routes modal-submit interactions to signupModal.handle.
 *
 * v1 has a single modal namespace (`m:o:<matchId>` -> signupModal). Unknown
 * customId prefixes get a generic friendly response — the dispatcher has
 * already deferReply'd (Pitfall 11) so editReply is safe.
 */
export async function handleModalSubmit(
    interaction: ModalSubmitInteraction,
): Promise<void> {
    const prefix = interaction.customId.split(':').slice(0, 2).join(':');
    if (prefix === 'm:o') {
        await signupModal.handle(interaction);
        return;
    }
    await interaction.editReply('Unknown modal type.');
}
