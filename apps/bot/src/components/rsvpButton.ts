// Trenchwars bot — RSVP button interaction handler.
//
// Source: .planning/phases/05-discord-bot-v1/05-10-PLAN.md task 2 (Wave 9),
// RESEARCH §Pattern 2 (modal-flow) + Pitfall 5 (customId encoding).
// Updated in Phase 10-05: clan_apply branch flipped from redirect-to-web stub
// to live api.post call; translateError extended with 3 new clan error codes.
//
// Routes the four `customIds.ts` variants:
//
//   match_open_signup_modal -> interaction.showModal(<role-input>)
//                              CRITICAL: this branch MUST be the INITIAL
//                              response (Pitfall 1 corollary). The dispatcher
//                              (events/interactionCreate.ts) detects modal-
//                              opening customIds via the 'm:o:' prefix and
//                              SKIPS the pre-defer step.
//
//   match_signup            -> api.post(/matches/{id}/signups, {role_id})
//                              interaction is already deferred by dispatcher;
//                              we editReply with success or translated error.
//
//   match_leave             -> api.delete(/matches/{id}/signups/{roleId})
//                              same dispatcher-deferred contract.
//
//   clan_apply              -> api.post(/clans/{clanId}/applications, {})
//                              NOTE: decoded.clanId is a UUID; the web route
//                              is slug-bound ({clan:slug}). This path posts
//                              the UUID directly — flagged for end-to-end
//                              verification in plan 10-07.
//
// translateError maps typed `BotApiErrorBody.error` codes to friendly copy.
// The current parser substring-matches on err.message because api.ts builds
// the message via template-string interpolation of the JSON body; a
// structured JSON parse is deferred to plan 05-12 polish (won't change the
// user-visible behaviour).

import { type ButtonInteraction, MessageFlags } from 'discord.js';

import { buildSignupModal } from './signupModal.js';
import { paginationButtons } from '../lib/buttons.js';
import { matchCard } from '../lib/embeds.js';
import { decodeButtonId } from '../lib/customIds.js';
import { api } from '../services/api.js';
import type { ClanData, ListMeta, PublicMatchData } from '../types/apiContracts.js';

export async function handle(interaction: ButtonInteraction): Promise<void> {
    const decoded = decodeButtonId(interaction.customId);

    // T-05-10-01 / T-05-08-04: malformed customId is a no-op. The dispatcher
    // has ALREADY deferred for non-modal-opening buttons (Pitfall 11), but
    // for safety we check interaction state — an Unknown button that arrives
    // as a modal-opener variant (m:o:...) would not have been deferred.
    if (decoded === null) {
        if (interaction.deferred) {
            await interaction.editReply('Unknown button.');
        } else {
            await interaction.reply({
                content: 'Unknown button.',
                flags: MessageFlags.Ephemeral,
            });
        }
        return;
    }

    if (decoded.kind === 'match_open_signup_modal') {
        // Pop the modal — this IS the initial response (Pitfall 1 corollary).
        // Dispatcher detected the 'm:o:' prefix and DID NOT pre-defer.
        const modal = buildSignupModal(decoded.matchId);
        await interaction.showModal(modal);
        return;
    }

    // Plan 12-04: Prev/Next page navigation.
    // Dispatcher detected the 'pg:' prefix and DID NOT pre-defer — interaction.update()
    // IS the initial response (it mutates the original component message in place).
    // T-12-04-T: decodeButtonId already validated listType + positive-integer page;
    //             any crafted pg: that decoded to null is handled by the Unknown button
    //             path above.
    if (decoded.kind === 'list_page') {
        try {
            if (decoded.listType === 'match') {
                const result = await api.get<{ data: PublicMatchData[]; meta: ListMeta }>(
                    `/matches?page=${decoded.page}`,
                    { actsAsDiscordId: interaction.user.id },
                );
                const { data: matches, meta } = result;

                if (matches.length === 0) {
                    await interaction.update({ content: 'No open matches.', embeds: [], components: [] });
                    return;
                }

                const embeds = matches.flatMap((m) => matchCard(m).embeds);

                if (meta.last_page <= 1) {
                    await interaction.update({ embeds, components: [] });
                    return;
                }

                const components = [paginationButtons('match', meta.current_page, meta.last_page)];
                const content = `Page ${meta.current_page} of ${meta.last_page}`;
                await interaction.update({ content, embeds, components });
            } else {
                // listType === 'clan'
                const result = await api.get<{ data: ClanData[]; meta: ListMeta }>(
                    `/clans?page=${decoded.page}`,
                    { actsAsDiscordId: interaction.user.id },
                );
                const { data: clans, meta } = result;

                const listText =
                    clans.length === 0
                        ? 'No clans.'
                        : clans.map((c) => `- [${c.tag}] ${c.name} (${c.slug})`).join('\n');

                if (clans.length === 0 || meta.last_page <= 1) {
                    await interaction.update({ content: listText, embeds: [], components: [] });
                    return;
                }

                const components = [paginationButtons('clan', meta.current_page, meta.last_page)];
                const content = `${listText}\nPage ${meta.current_page} of ${meta.last_page}`;
                await interaction.update({ content, embeds: [], components });
            }
        } catch (err) {
            // Error path: must still call update() to acknowledge the interaction;
            // leaving it unacknowledged causes "application did not respond" in Discord.
            await interaction.update({
                content: translateError(err),
                embeds: [],
                components: [],
            });
        }
        return;
    }

    if (decoded.kind === 'match_signup') {
        try {
            await api.post(
                `/matches/${decoded.matchId}/signups`,
                { game_role_id: decoded.gameRoleId },
                { actsAsDiscordId: interaction.user.id },
            );
            await interaction.editReply('Signed up to the match.');
        } catch (err) {
            await interaction.editReply(translateError(err));
        }
        return;
    }

    if (decoded.kind === 'match_leave') {
        try {
            await api.delete(
                `/matches/${decoded.matchId}/signups/${decoded.gameRoleId}`,
                { actsAsDiscordId: interaction.user.id },
            );
            await interaction.editReply('You have left the match.');
        } catch (err) {
            await interaction.editReply(translateError(err));
        }
        return;
    }

    if (decoded.kind === 'clan_apply') {
        // Posts decoded.clanId (UUID) — the web route is slug-bound; flagged
        // for end-to-end verification in plan 10-07.
        try {
            await api.post(
                `/clans/${decoded.clanId}/applications`,
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

/**
 * translateError - maps API error responses to friendly user copy.
 *
 * apps/bot/src/services/api.ts throws Error with a message containing the
 * full JSON response body (truncated to 500 chars). We substring-match on
 * the canonical error codes from apps/web/lang/en/bot.php bot.errors.* :
 *
 *   match_not_open         -> "This match is not open for signups."
 *   capacity_full          -> "This role is full."
 *   tag_restricted         -> "Your clan tags are not permitted on this match."
 *   already_signed_up      -> "You are already signed up to this match."
 *   clan_not_recruiting    -> "This clan is not accepting applications."
 *   already_in_clan        -> "You are already a member of a clan."
 *   duplicate_application  -> "You already have a pending application to this clan."
 *
 * Anything else falls through to a generic "Failed: <scrubbed-message>" —
 * the original message is already token-scrubbed by api.ts (T-05-08-01
 * mitigation) so it's safe to surface.
 */
export function translateError(err: unknown): string {
    const msg = err instanceof Error ? err.message : String(err);
    if (msg.includes('match_not_open')) {
        return 'This match is not open for signups.';
    }
    if (msg.includes('capacity_full')) {
        return 'This role is full.';
    }
    if (msg.includes('tag_restricted')) {
        return 'Your clan tags are not permitted on this match.';
    }
    if (msg.includes('already_signed_up')) {
        return 'You are already signed up to this match.';
    }
    if (msg.includes('clan_not_recruiting')) {
        return 'This clan is not accepting applications.';
    }
    if (msg.includes('already_in_clan')) {
        return 'You are already a member of a clan.';
    }
    if (msg.includes('duplicate_application')) {
        return 'You already have a pending application to this clan.';
    }
    return `Failed: ${msg.slice(0, 200)}`;
}
