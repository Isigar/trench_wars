// Trenchwars bot — pure page-payload builders for paginated list commands.
//
// Source: Phase 12 fix — WR-02 deduplication + BL-01 embed-cap + BL-02 out-of-range
//         clamp + WR-01 clan content truncation.
//
// These functions perform the fetch + envelope read + safety checks and return
// a Discord message payload object (`{ content?, embeds, components }`).
// They are PURE with respect to the interaction — callers decide whether to
// call editReply() (command handler) or update() (button handler).
//
// Safety invariants enforced here:
//   BL-01: match pages fetch with limit=5 (Discord hard cap is 10 embeds/message;
//          5 is conservative + readable). The embed array is additionally capped
//          at MAX_EMBEDS=10 as a belt-and-suspenders guard.
//   BL-02: if the requested page > meta.last_page, re-fetches meta.last_page.
//          This recovers stale pagination buttons gracefully instead of stranding
//          the user on a dead-end "No open matches." with no navigation.
//   WR-01: clan list content string is sliced to DISCORD_MESSAGE_MAX=2000 chars.

import { ActionRowBuilder, ButtonBuilder } from 'discord.js';

import { paginationButtons } from './buttons.js';
import { matchCard } from './embeds.js';
import { api } from '../services/api.js';
import type { ClanData, ListMeta, PublicMatchData } from '../types/apiContracts.js';

// Discord hard caps.
const MAX_EMBEDS = 10;
const DISCORD_MESSAGE_MAX = 2000;

// Safe page size for match lists — keeps embed count well under Discord's 10-embed cap.
const MATCH_PAGE_LIMIT = 5;
// Safe page size for clan lists — keeps plain-text content under Discord's 2000-char cap.
const CLAN_PAGE_LIMIT = 20;

export interface ListPagePayload {
    content?: string;
    embeds: object[];
    components: ActionRowBuilder<ButtonBuilder>[];
}

// ---------------------------------------------------------------------------
// buildMatchListPage
// ---------------------------------------------------------------------------

/**
 * Fetches /matches?page=N&limit=MATCH_PAGE_LIMIT and constructs the Discord
 * message payload.
 *
 * BL-01: limit=5 at fetch time; embeds additionally capped to MAX_EMBEDS.
 * BL-02: if page > meta.last_page, re-fetches and renders meta.last_page.
 *
 * Returns null-content payload string "No open matches." when truly empty
 * (last_page === 0 or total === 0).
 */
export async function buildMatchListPage(
    page: number,
    actsAsDiscordId: string,
): Promise<ListPagePayload | { content: string; embeds: []; components: [] }> {
    const result = await api.get<{ data: PublicMatchData[]; meta: ListMeta }>(
        `/matches?page=${page}&limit=${MATCH_PAGE_LIMIT}`,
        { actsAsDiscordId },
    );
    let { data: matches, meta } = result;

    // BL-02: out-of-range page — clamp to last_page and re-fetch.
    if (matches.length === 0 && meta.last_page >= 1 && page > meta.last_page) {
        const clamped = await api.get<{ data: PublicMatchData[]; meta: ListMeta }>(
            `/matches?page=${meta.last_page}&limit=${MATCH_PAGE_LIMIT}`,
            { actsAsDiscordId },
        );
        matches = clamped.data;
        meta = clamped.meta;
    }

    if (matches.length === 0) {
        return { content: 'No open matches.', embeds: [], components: [] };
    }

    // BL-01: cap embeds to MAX_EMBEDS (belt-and-suspenders).
    const embeds = matches.flatMap((m) => matchCard(m).embeds).slice(0, MAX_EMBEDS);

    if (meta.last_page <= 1) {
        return { embeds, components: [] };
    }

    const components = [paginationButtons('match', meta.current_page, meta.last_page)];
    const content = `Page ${meta.current_page} of ${meta.last_page}`;
    return { content, embeds, components };
}

// ---------------------------------------------------------------------------
// buildClanListPage
// ---------------------------------------------------------------------------

/**
 * Fetches /clans?page=N&limit=CLAN_PAGE_LIMIT and constructs the Discord
 * message payload.
 *
 * BL-02: if page > meta.last_page, re-fetches and renders meta.last_page.
 * WR-01: content string is sliced to DISCORD_MESSAGE_MAX.
 */
export async function buildClanListPage(
    page: number,
    actsAsDiscordId: string,
): Promise<ListPagePayload | { content: string; embeds: []; components: [] }> {
    const result = await api.get<{ data: ClanData[]; meta: ListMeta }>(
        `/clans?page=${page}&limit=${CLAN_PAGE_LIMIT}`,
        { actsAsDiscordId },
    );
    let { data: clans, meta } = result;

    // BL-02: out-of-range page — clamp to last_page and re-fetch.
    if (clans.length === 0 && meta.last_page >= 1 && page > meta.last_page) {
        const clamped = await api.get<{ data: ClanData[]; meta: ListMeta }>(
            `/clans?page=${meta.last_page}&limit=${CLAN_PAGE_LIMIT}`,
            { actsAsDiscordId },
        );
        clans = clamped.data;
        meta = clamped.meta;
    }

    if (clans.length === 0) {
        return { content: 'No clans.', embeds: [], components: [] };
    }

    const listText = clans.map((c) => `- [${c.tag}] ${c.name} (${c.slug})`).join('\n');

    if (meta.last_page <= 1) {
        // WR-01: slice to Discord message cap.
        return { content: listText.slice(0, DISCORD_MESSAGE_MAX), embeds: [], components: [] };
    }

    const components = [paginationButtons('clan', meta.current_page, meta.last_page)];
    // WR-01: include page indicator, then slice entire content.
    const content = `${listText}\nPage ${meta.current_page} of ${meta.last_page}`.slice(
        0,
        DISCORD_MESSAGE_MAX,
    );
    return { content, embeds: [], components };
}
