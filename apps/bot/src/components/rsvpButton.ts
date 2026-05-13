// Trenchwars bot — RSVP button interaction handler.
//
// Source: .planning/phases/05-discord-bot-v1/05-10-PLAN.md task 2 (Wave 9),
// RESEARCH §Pattern 2 (modal-flow) + Pitfall 5 (customId encoding).
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
//   clan_apply              -> v1 redirect-to-web stub (plan 05-09 D-05-09-B)
//                              — the /api/bot/clans/{slug}/applications
//                              endpoint is RESEARCH Q2 "future v1+"; the
//                              button reply mirrors the slash-command stub.
//
// translateError maps the 4 typed `BotApiErrorBody.error` codes returned by
// the web side's MatchSignupController (plan 05-04) to friendly user copy.
// The current parser substring-matches on err.message because api.ts builds
// the message via template-string interpolation of the JSON body; a
// structured JSON parse is deferred to plan 05-12 polish (won't change the
// user-visible behaviour).

import { type ButtonInteraction, MessageFlags } from 'discord.js';

import { buildSignupModal } from './signupModal.js';
import { decodeButtonId } from '../lib/customIds.js';
import { api } from '../services/api.js';

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
        // v1 redirect-to-web stub (mirrors plan 05-09 /clan apply slash
        // command). The endpoint is RESEARCH Q2 deferred to Phase 6+.
        await interaction.editReply(
            'Clan applications are managed on the website.',
        );
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
 *   match_not_open    -> "This match is not open for signups."
 *   capacity_full     -> "This role is full."
 *   tag_restricted    -> "Your clan tags are not permitted on this match."
 *   already_signed_up -> "You are already signed up to this match."
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
    return `Failed: ${msg.slice(0, 200)}`;
}
