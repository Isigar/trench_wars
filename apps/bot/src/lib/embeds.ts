// Trenchwars bot — canonical EmbedBuilder factories.
//
// Source: .planning/phases/05-discord-bot-v1/05-10-PLAN.md task 1 (Wave 9),
// RESEARCH §Example 3 (matchCard verbatim shape) + plan 05-01 bot.embeds.*
// i18n keys.
//
// Three exports:
//
//   matchCard(m: PublicMatchData)   - SC-1 / SC-3 — embed + optional Sign-up
//                                     ActionRow (status='open' only)
//   clanCard(c: ClanData)           - SC-1 — informational embed, no buttons
//   profileCard(p: PublicPlayerData) - SC-1 — informational embed, no buttons
//
// Discord field limits enforced at build time (Pitfall: 400 invalid embed
// otherwise):
//   - title          256
//   - description    4096 (we clamp to 2000 for safety/readability)
//   - field name     256
//   - field value    1024
//   - total embed    6000
//
// The matchCard "Scheduled" field uses Discord's <t:UNIX_TS:F> timestamp tag
// which auto-localizes to the viewing user's timezone. RESEARCH Example 3
// canonical shape.
//
// NOTE on DTO shape: the actual PublicMatchData from spatie/laravel-data
// (see apps/web/app/Data/PublicMatchData.php) ships only the SCALAR projection —
// host_clan_id (not host_clan.name), game_match_type_id (not nested object),
// no slots[]. The plan 05-10 <interfaces> block ASPIRATIONALLY referenced
// nested objects; we render with what the contract actually delivers (Phase
// 6+ may eager-load + extend the DTO to include the nested fields, at which
// point this card can be enriched without a breaking change).
//
// Threat refs:
//   T-05-10-02 (oversize field rejected by Discord 400) - .slice() guards
//   T-05-10-04 (private fields leaked)                  - upstream privacy
//                                                         gate already
//                                                         collapsed them to
//                                                         null; conditional
//                                                         addFields skips
//                                                         null entries
//   T-05-10-07 (@everyone mention in title)             - outbound worker
//                                                         (plan 05-11) sends
//                                                         allowed_mentions:
//                                                         {parse: []}; this
//                                                         file's embeds ship
//                                                         on ephemeral slash
//                                                         command replies so
//                                                         mention parsing is
//                                                         already neutralized.

import {
    ActionRowBuilder,
    ButtonBuilder,
    EmbedBuilder,
} from 'discord.js';

import { openSignupModalButton } from './buttons.js';
import { statusColor } from './colors.js';
import type {
    ClanData,
    ClanTagData,
    PublicMatchData,
    PublicPlayerData,
} from '../types/apiContracts.js';

// Discord limits (https://discord.com/developers/docs/resources/channel#embed-object-embed-limits).
const TITLE_MAX = 256;
const DESC_MAX = 2000;
const FIELD_VALUE_MAX = 1024;

/**
 * matchCard - SC-1 / SC-3 canonical embed for /match list and /match info.
 *
 * Returns `{embeds, components}` consumed by `interaction.editReply()`. The
 * Sign-up button is present ONLY when `m.status === 'open'`; the v1 button
 * pops a single modal (plan 05-12 polish may swap to a StringSelectMenu
 * pre-populated from the match's role list once the DTO eager-loads slots).
 */
export function matchCard(m: PublicMatchData): {
    embeds: EmbedBuilder[];
    components: ActionRowBuilder<ButtonBuilder>[];
} {
    const title = (m.title?.en ?? `Match ${m.id}`).slice(0, TITLE_MAX);
    const scheduledTs =
        m.scheduled_at !== null && m.scheduled_at !== ''
            ? Math.floor(new Date(m.scheduled_at).getTime() / 1000)
            : null;

    const description = (m.description?.en ?? '').slice(0, DESC_MAX);

    const fields: Array<{ name: string; value: string; inline?: boolean }> = [
        { name: 'Status', value: m.status, inline: true },
    ];
    if (scheduledTs !== null) {
        fields.push({
            name: 'Scheduled',
            value: `<t:${scheduledTs}:F>`,
            inline: true,
        });
    }
    if (m.host_clan_id !== null) {
        fields.push({
            name: 'Host clan',
            value: m.host_clan_id.slice(0, FIELD_VALUE_MAX),
            inline: true,
        });
    }
    fields.push({
        name: 'Match type',
        value: m.game_match_type_id.slice(0, FIELD_VALUE_MAX),
        inline: false,
    });

    const embed = new EmbedBuilder()
        .setColor(statusColor(m.status))
        .setTitle(title)
        .addFields(...fields)
        .setFooter({ text: `Match id: ${m.id}` });

    if (description !== '') {
        embed.setDescription(description);
    }

    const components: ActionRowBuilder<ButtonBuilder>[] = [];
    if (m.status === 'open') {
        components.push(
            new ActionRowBuilder<ButtonBuilder>().addComponents(
                openSignupModalButton(m.id),
            ),
        );
    }

    return { embeds: [embed], components };
}

/**
 * clanCard - SC-1 informational embed for /clan info.
 *
 * No buttons in v1 (the /clan apply slash command is a redirect-to-web stub
 * per plan 05-09 D-05-09-B; once Phase 6+ ships the endpoint a join button
 * can land here).
 */
export function clanCard(c: ClanData): { embeds: EmbedBuilder[] } {
    const title = (c.name ?? c.slug).slice(0, TITLE_MAX);
    const description = (c.description?.en ?? '').slice(0, DESC_MAX);

    const embed = new EmbedBuilder()
        .setColor(0x0078d4) // brand blue
        .setTitle(title);

    if (description !== '') {
        embed.setDescription(description);
    }

    const fields: Array<{ name: string; value: string; inline?: boolean }> = [
        { name: 'Tag', value: c.tag, inline: true },
        { name: 'Slug', value: c.slug, inline: true },
        { name: 'Members', value: String(c.active_member_count), inline: true },
    ];
    if (c.tags.length > 0) {
        const tagList = c.tags
            .map((t: ClanTagData) => `\`${t.slug}\``)
            .join(' ');
        fields.push({
            name: 'Tags',
            value: tagList.slice(0, FIELD_VALUE_MAX),
            inline: false,
        });
    }
    embed.addFields(...fields);
    embed.setFooter({ text: `Clan id: ${c.id}` });

    return { embeds: [embed] };
}

/**
 * profileCard - SC-1 informational embed for /profile and /me.
 *
 * Privacy enforcement is UPSTREAM — the web side's PlayerPrivacyGate
 * collapses tier-restricted fields to undefined before serializing the DTO.
 * This factory just renders what it received; conditional field-add skips
 * undefined/null entries so private fields never appear in the embed.
 *
 * T-05-10-04 disposition: mitigated (privacy gate runs server-side).
 */
export function profileCard(p: PublicPlayerData): { embeds: EmbedBuilder[] } {
    const title = `Player: ${p.slug}`.slice(0, TITLE_MAX);

    const embed = new EmbedBuilder()
        .setColor(0x666666)
        .setTitle(title);

    const fields: Array<{ name: string; value: string; inline?: boolean }> = [
        { name: 'Display name', value: p.displayName, inline: true },
    ];
    if (p.countryCode !== null) {
        fields.push({
            name: 'Country',
            value: p.countryCode,
            inline: true,
        });
    }
    if (p.discordTag !== undefined && p.discordTag !== null) {
        fields.push({
            name: 'Discord',
            value: p.discordTag,
            inline: true,
        });
    }
    if (p.bio !== undefined && p.bio !== null) {
        const bioEn = p.bio.en;
        if (typeof bioEn === 'string' && bioEn !== '') {
            fields.push({
                name: 'Bio',
                value: bioEn.slice(0, FIELD_VALUE_MAX),
                inline: false,
            });
        }
    }

    embed.addFields(...fields);
    embed.setFooter({ text: `Player id: ${p.id}` });

    return { embeds: [embed] };
}
