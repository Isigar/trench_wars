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
    if (
        typeof m.game_match_type_id === 'string' &&
        m.game_match_type_id !== ''
    ) {
        fields.push({
            name: 'Match type',
            value: m.game_match_type_id.slice(0, FIELD_VALUE_MAX),
            inline: false,
        });
    }

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

// ---------------------------------------------------------------------------
// Phase 6 tournament announce + bracket result embeds (plan 06-13 task 2).
//
// Source: .planning/phases/06-tournaments-brackets/06-13-PLAN.md <interfaces>.
//
// Two builders, both consuming the canonical payload shapes from
// App\Support\DiscordOutboundPayloadBuilder::buildTournamentAnnounce +
// ::buildBracketResult (web side, plans 06-10 + 06-08).
//
// Open Question 5 LOCKED inline (consistent with plans 06-08 + 06-10): the
// Phase 5 outbound table now carries 3 distinct kinds — tournament_announce,
// tournament_announce_update, bracket_result_announce. Distinct kinds give
// the bot worker per-kind dispatch logic AND let admins filter the Filament
// DiscordOutboundMessageResource by message_type for debugging.
//
// Threat refs:
//   T-06-13-03 (XSS via embed title) — EmbedBuilder.setTitle / setDescription
//                                      escape Markdown by default; the trust
//                                      boundary is at admin/spatie-permission
//                                      (plan 06-11) where the strings are set.
//   T-06-13-04 (wrong channel)        — outbound.channel_id is server-side
//                                      resolved at write time; bot reads
//                                      row.channel_id verbatim.
//   T-06-13-05 (WEB_URL unset in CI)  — falls back to 'http://localhost'; tests
//                                      assert URL contains the slug, not host.
// ---------------------------------------------------------------------------

/**
 * Payload shape emitted by DiscordOutboundPayloadBuilder::buildTournamentAnnounce
 * (plan 06-10). Re-declared here as a runtime contract — the bot worker reads
 * the JSONB payload field verbatim and shape-checks it at dispatch time.
 *
 * `title` is a JSONB map {locale: text} (Spatie translatable). For now the bot
 * only renders the 'en' fallback; Phase 9 polish will resolve per-server locale.
 */
export interface TournamentAnnouncePayload {
    kind: 'tournament_announce' | 'tournament_announce_update';
    tournament_id: string | null;
    tournament_slug: string | null;
    title: Record<string, string | null>;
    format: string | null;
    status: string | null;
    starts_at: string | null;
    ends_at: string | null;
    organiser_user_id: string | null;
    max_participants: number | null;
    is_public: boolean;
}

/**
 * Payload shape emitted by DiscordOutboundPayloadBuilder::buildBracketResult
 * (plan 06-08). Bracket-result-announce fires from
 * BracketAdvancementService::advance when a tournament match resolves and a
 * winner propagates forward through the bracket tree.
 */
export interface BracketResultPayload {
    kind: 'bracket_result_announce';
    tournament_id: string | null;
    tournament_slug: string | null;
    tournament_title: string | null;
    stage_id: string | null;
    stage_type: string | null;
    bracket_id: string | null;
    round_number: number | null;
    position: number | null;
    winner_participant_id: string | null;
    winner_clan_id: string | null;
    winner_clan_name: string | null;
    participant_a_clan_name: string | null;
    participant_b_clan_name: string | null;
}

// 24-bit RGB constants — Phase 1 60/30/10 trench-military palette analogs.
const COLOR_TOURNAMENT_PRIMARY = 0x4f46e5; // indigo-600 — distinct from match green/blue
const COLOR_BRACKET_RESULT = 0x10b981; // emerald-500 — winner celebration

/**
 * Resolve the public web URL for tournament deep links. Falls back to
 * 'http://localhost' when WEB_URL is unset (test/CI contract).
 */
function webUrl(): string {
    const v = process.env.WEB_URL;
    return v === undefined || v === '' ? 'http://localhost' : v;
}

/**
 * buildTournamentAnnounceEmbed — SC-3 / SC-5 Discord embed for the
 * tournament_announce + tournament_announce_update outbound kinds.
 *
 * Defensive defaults (Phase 5 D-05-10-F idiom): missing title falls back to
 * 'Tournament'; missing format/status render as '—'; missing slug yields a
 * link to the tournament directory rather than 404.
 */
export function buildTournamentAnnounceEmbed(
    payload: TournamentAnnouncePayload,
    locale: string = 'en',
): EmbedBuilder {
    const titleByLocale = payload.title[locale] ?? payload.title.en ?? null;
    const title = (titleByLocale ?? 'Tournament').slice(0, TITLE_MAX);

    const slugSuffix =
        typeof payload.tournament_slug === 'string' &&
        payload.tournament_slug !== ''
            ? `/${payload.tournament_slug}`
            : '';
    const url = `${webUrl()}/tournaments${slugSuffix}`;

    const fields: Array<{ name: string; value: string; inline?: boolean }> = [
        {
            name: 'Format',
            value: (payload.format ?? '—').slice(0, FIELD_VALUE_MAX),
            inline: true,
        },
        {
            name: 'Status',
            value: (payload.status ?? '—').slice(0, FIELD_VALUE_MAX),
            inline: true,
        },
        {
            name: 'Max participants',
            value:
                payload.max_participants === null
                    ? '—'
                    : String(payload.max_participants),
            inline: true,
        },
    ];

    if (payload.starts_at !== null && payload.starts_at !== '') {
        const ts = Math.floor(new Date(payload.starts_at).getTime() / 1000);
        // Number.isFinite(NaN) is false — guard against bad ISO strings.
        if (Number.isFinite(ts)) {
            fields.push({
                name: 'Starts at',
                value: `<t:${ts}:F>`,
                inline: false,
            });
        }
    }

    const embed = new EmbedBuilder()
        .setColor(COLOR_TOURNAMENT_PRIMARY)
        .setTitle(title)
        .setURL(url)
        .addFields(...fields);

    if (
        typeof payload.tournament_id === 'string' &&
        payload.tournament_id !== ''
    ) {
        embed.setFooter({ text: `Tournament id: ${payload.tournament_id}` });
    }

    return embed;
}

/**
 * buildBracketResultEmbed — SC-3 Discord embed for the bracket_result_announce
 * outbound kind. Posts a short "X defeated Y" line when the winner is known,
 * or "Result pending" when the bracket has both participants but no recorded
 * winner yet (defensive guard — should not happen in practice because the
 * outbound row only fires after BracketAdvancementService records a winner).
 */
export function buildBracketResultEmbed(
    payload: BracketResultPayload,
): EmbedBuilder {
    const round = payload.round_number ?? 0;
    const position = payload.position ?? 0;
    const title = `Round ${round} — Match ${position}`.slice(0, TITLE_MAX);

    const slugSuffix =
        typeof payload.tournament_slug === 'string' &&
        payload.tournament_slug !== ''
            ? `/${payload.tournament_slug}`
            : '';
    const url = `${webUrl()}/tournaments${slugSuffix}`;

    const a = payload.participant_a_clan_name;
    const b = payload.participant_b_clan_name;
    const winner = payload.winner_clan_name;

    let description: string;
    if (winner !== null && winner !== '') {
        // Pick the loser by comparing winner to side A.
        const loser =
            a === winner ? (b ?? '?') : (a ?? '?');
        description = `**${winner}** defeated ${loser}`;
    } else {
        description = 'Result pending';
    }

    const embed = new EmbedBuilder()
        .setColor(COLOR_BRACKET_RESULT)
        .setTitle(title)
        .setURL(url)
        .setDescription(description.slice(0, DESC_MAX));

    if (
        typeof payload.tournament_title === 'string' &&
        payload.tournament_title !== ''
    ) {
        embed.addFields({
            name: 'Tournament',
            value: payload.tournament_title.slice(0, FIELD_VALUE_MAX),
            inline: false,
        });
    }

    if (
        typeof payload.bracket_id === 'string' &&
        payload.bracket_id !== ''
    ) {
        embed.setFooter({ text: `Bracket id: ${payload.bracket_id}` });
    }

    return embed;
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
