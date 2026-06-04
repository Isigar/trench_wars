// Trenchwars bot — central interaction dispatcher.
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 2 (Wave 7)
//         + 05-10-PLAN.md task 2 (Wave 9) — component routing.
//
// Single Events.InteractionCreate listener that routes every incoming
// interaction to the correct handler:
//
//   isChatInputCommand()   -> commands.get(name)?.execute(interaction)
//                             - per-command handler defers itself; modal-
//                               opening branches (/match signup) SKIP defer
//                               (Pitfall 1 corollary).
//
//   isModalSubmit()        -> deferReply (Pitfall 11), then handleModalSubmit.
//                             - The modal submit interaction gets a FRESH 3s
//                               window — Discord treats it as independent
//                               of the slash command / button that opened
//                               the modal.
//
//   isButton()             -> SPLIT routing (plan 05-10 refinement):
//                             - customId prefixed 'm:o:' (modal-opening)
//                               -> NO pre-defer; handleButton invokes
//                                  showModal as the INITIAL response.
//                             - everything else (m:s:, m:l:, c:a:)
//                               -> deferReply (Pitfall 11), then
//                                  handleButton -> api.post/delete +
//                                  editReply.
//
//   isStringSelectMenu()   -> deferReply (Pitfall 11). Currently no select
//                             menus are emitted (matchCard buttons only); a
//                             future polish (plan 05-12) may swap the role
//                             text input for a StringSelectMenu, at which
//                             point a per-prefix dispatch lands here.
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
//
// Refactor in plan 05-10: the button branch no longer pre-defers
// unconditionally — modal-opening buttons (customId 'm:o:...') require an
// undeferred interaction for showModal() to succeed. The customId-prefix
// peek is a safe lookup (no decoding cost).

import { type Client, Events, MessageFlags } from 'discord.js';

import { commands } from '../commands/index.js';
import { handleButton, handleModalSubmit } from '../components/index.js';

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
                await handleModalSubmit(interaction);
                return;
            }

            // Plan 05-10 refinement + plan 12-04: split button handling by customId prefix.
            // Modal-opening buttons (m:o:...) cannot be pre-deferred — the
            // showModal() call IS the initial response. Pagination buttons
            // (pg:...) also cannot be pre-deferred — interaction.update() IS
            // the initial response (it edits the original component message in
            // place; deferReply would claim the window first and block update()).
            // Everything else is pre-deferred per Pitfall 11.
            if (interaction.isButton()) {
                if (
                    interaction.customId.startsWith('m:o:') ||
                    interaction.customId.startsWith('pg:')
                ) {
                    await handleButton(interaction);
                    return;
                }
                await interaction.deferReply({ flags: MessageFlags.Ephemeral });
                await handleButton(interaction);
                return;
            }

            // String-select-menu interactions also need a fresh deferReply.
            // No v1 emitters yet; placeholder kept for plan 05-12 polish.
            if (interaction.isStringSelectMenu()) {
                await interaction.deferReply({ flags: MessageFlags.Ephemeral });
                await interaction.editReply(
                    'Select-menu handlers are not yet wired.',
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
