// Trenchwars bot — central interaction dispatcher.
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 2 (Wave 7).
// Single Events.InteractionCreate listener that routes every incoming
// interaction to the correct handler:
//
//   isChatInputCommand()   -> commands.get(name)?.execute(interaction)
//   isModalSubmit()        -> deferReply (Pitfall 11), placeholder editReply
//                             (plan 05-10 ships the real submit handler)
//   isButton() / isStringSelectMenu()
//                          -> deferReply (Pitfall 11), placeholder editReply
//                             (plan 05-10 ships decodeButtonId routing)
//
// Pitfall 11: modal submit interactions get a FRESH 3s window — Discord
// treats them as independent of the slash command that opened the modal.
// We MUST deferReply at the dispatcher level before any per-handler work.
//
// Top-level try/catch is best-effort error reply with state-aware fallback:
//   - if interaction is unanswered (!replied && !deferred): reply ephemeral
//   - if interaction is already deferred: editReply
//   - otherwise: log only (Discord rejected the response window already)
//
// Threat mitigation T-05-09-02 (deferReply forgotten): the per-command
// modules already defer themselves; the dispatcher is the SECOND layer of
// defense for modals + buttons. If a future command forgets to defer, the
// 3s window will expire and Discord shows "the application did not
// respond" — observable in operator smoke (plan 05-13).

import { type Client, Events, MessageFlags } from 'discord.js';

import { commands } from '../commands/index.js';

export function registerInteractionHandler(client: Client): void {
    client.on(Events.InteractionCreate, async (interaction) => {
        try {
            if (interaction.isChatInputCommand()) {
                const cmd = commands.get(interaction.commandName);
                if (cmd === undefined) {
                    await interaction.reply({
                        content: 'Unknown command.',
                        flags: MessageFlags.Ephemeral,
                    });
                    return;
                }
                await cmd.execute(interaction);
                return;
            }

            // Pitfall 11: modal submit gets a fresh 3s window — defer FIRST.
            if (interaction.isModalSubmit()) {
                await interaction.deferReply({ flags: MessageFlags.Ephemeral });
                // plan 05-10 will dispatch to signupModal.handleSubmit here.
                await interaction.editReply(
                    'Modal submit handlers ship in plan 05-10.',
                );
                return;
            }

            // Button + string-select-menu interactions also need a fresh
            // deferReply; plan 05-10 wires decodeButtonId routing here.
            if (interaction.isButton() || interaction.isStringSelectMenu()) {
                await interaction.deferReply({ flags: MessageFlags.Ephemeral });
                await interaction.editReply(
                    'Component handlers ship in plan 05-10.',
                );
                return;
            }
        } catch (err) {
            console.error('[bot/interactionCreate]', err);
            // Best-effort error reply — state-aware fallback. Suppress
            // exceptions from the recovery path (interaction may already
            // be in a terminal state if the window expired).
            if (!interaction.isRepliable()) {
                return;
            }
            if (!interaction.replied && !interaction.deferred) {
                await interaction
                    .reply({
                        content: 'An error occurred.',
                        flags: MessageFlags.Ephemeral,
                    })
                    .catch(() => {
                        /* swallow */
                    });
            } else if (interaction.deferred) {
                await interaction
                    .editReply('An error occurred.')
                    .catch(() => {
                        /* swallow */
                    });
            }
        }
    });
}
