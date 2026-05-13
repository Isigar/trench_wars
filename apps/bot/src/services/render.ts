// Trenchwars bot — OutboundRow renderer / dispatcher.
//
// Source: .planning/phases/05-discord-bot-v1/05-11-PLAN.md task 1 (Wave 10).
// Converts a `discord_outbound_messages` row into an actual Discord side
// effect. The outbound worker (apps/bot/src/services/outbound.ts) calls
// render(client, row) for each pending row and uses the returned
// discordMessageId to ack the row via /outbound-messages/{id}/sent.
//
// Two dispatch paths:
//
//   message_type='match_announce' — payload is the
//     DiscordOutboundPayloadBuilder shape (plan 05-05). Re-fetches the match
//     DTO from /api/bot/matches/{id} to render the full matchCard, then
//     either edits the prior message (kind='match_announce_update' with a
//     non-null prior_sent_message_id) or posts a fresh one.
//
//   message_type='role_sync' — payload contains
//     {discord_user_id, discord_role_id, action}. Uses guild.members.fetch
//     + member.roles.add()/remove(). The discord.js REST manager handles
//     Discord's 429 rate-limit + per-bucket retry automatically (Pattern 2
//     verified in RESEARCH).
//
// Safety:
//
//   T-05-10-07 / T-05-11-05 (defence-in-depth against @everyone abuse via
//   crafted match titles) — every channel.send / msg.edit call passes
//   allowed_mentions: { parse: [] }. This neutralises @everyone, @here,
//   role mentions, and user mentions at the Discord API layer regardless
//   of what the embed body contains.
//
//   T-05-11-06 (operator points discord_announce_channel_id at a wrong /
//   unfetchable channel) — client.channels.fetch returns null on 404 or
//   throws on other errors; the throw is caught by the worker, the row is
//   marked failed with the error string, and the operator sees the
//   failure in Filament's DiscordOutboundMessageResource (plan 05-07).
//
//   T-05-11-04 / T-05-11-08 (role_sync echo loop) — handled WEB-SIDE
//   (Pitfall 10, plan 05-04 BotApiDiscordEventController applies a 60s
//   suppression window keyed on payload->discord_user_id +
//   payload->discord_role_id). The bot side has no special-casing here.
//
// Return shape: { discordMessageId } — for match_announce this is the
// Discord channel message id; for role_sync it is a synthetic
// "role_sync:<action>:<userId>:<roleId>" string. The web side stores it
// verbatim into discord_outbound_messages.sent_message_id; only the
// match_announce path actually uses that value later (to edit the same
// message on status changes via match_announce_update).

import type {
    Client,
    Message,
    TextBasedChannel,
} from 'discord.js';

import { env } from '../env.js';
import {
    buildBracketResultEmbed,
    buildTournamentAnnounceEmbed,
    matchCard,
    type BracketResultPayload,
    type TournamentAnnouncePayload,
} from '../lib/embeds.js';
import type {
    OutboundRow,
    PublicMatchData,
} from '../types/apiContracts.js';
import { api } from './api.js';

interface RenderResult {
    discordMessageId: string;
}

interface MatchAnnouncePayload {
    kind: 'match_announce_new' | 'match_announce_update';
    match_id: string;
    prior_sent_message_id?: string | null;
}

interface RoleSyncPayload {
    discord_user_id: string;
    discord_role_id: string;
    action: 'add' | 'remove';
}

export async function render(
    client: Client,
    row: OutboundRow,
): Promise<RenderResult> {
    if (row.message_type === 'match_announce') {
        return renderMatchAnnounce(client, row);
    }
    if (row.message_type === 'role_sync') {
        return renderRoleSync(client, row);
    }
    if (
        row.message_type === 'tournament_announce' ||
        row.message_type === 'tournament_announce_update'
    ) {
        return renderTournamentAnnounce(client, row);
    }
    if (row.message_type === 'bracket_result_announce') {
        return renderBracketResultAnnounce(client, row);
    }
    throw new Error(
        `[bot/render] Unknown message_type: ${row.message_type}`,
    );
}

async function renderMatchAnnounce(
    client: Client,
    row: OutboundRow,
): Promise<RenderResult> {
    const payload = row.payload as MatchAnnouncePayload;
    const matchId = payload.match_id;
    if (typeof matchId !== 'string' || matchId === '') {
        throw new Error(
            `[bot/render] match_announce payload missing match_id (row ${row.id})`,
        );
    }

    // Re-fetch the canonical PublicMatchData DTO so the card reflects current
    // state (status, scheduled_at, title.en) at dispatch time rather than at
    // observer-fire time. The web side serves /api/bot/matches/{id} unauthed-
    // as-user (no actsAsDiscordId) for the bot's service-level dispatch.
    const match = await api.get<PublicMatchData>(`/matches/${matchId}`);

    const channel = await client.channels.fetch(row.channel_id);
    if (
        channel === null ||
        !channel.isTextBased() ||
        !('send' in channel)
    ) {
        throw new Error(
            `[bot/render] Channel ${row.channel_id} not found or not text-based`,
        );
    }
    const sendable = channel as TextBasedChannel & {
        send: (opts: unknown) => Promise<Message>;
        messages: { fetch: (id: string) => Promise<Message> };
    };

    const { embeds, components } = matchCard(match);

    // T-05-10-07 / T-05-11-05: allowed_mentions:{parse:[]} on every dispatch
    // suppresses @everyone / @here / role / user mention parsing regardless
    // of what the embed title or description contains.
    const messagePayload = {
        embeds,
        components,
        allowed_mentions: { parse: [] as never[] },
    };

    if (
        payload.kind === 'match_announce_update' &&
        typeof payload.prior_sent_message_id === 'string' &&
        payload.prior_sent_message_id !== ''
    ) {
        const priorId = payload.prior_sent_message_id;
        try {
            const msg = await sendable.messages.fetch(priorId);
            await msg.edit(messagePayload);
            return { discordMessageId: priorId };
        } catch {
            // Fallthrough: prior message gone (deleted by mod, channel
            // wiped, etc.) — post a fresh one. The web side's
            // BotApiOutboundController will record the new sent_message_id;
            // subsequent updates will edit THIS message until it too is
            // deleted.
        }
    }

    const sent = await sendable.send(messagePayload);
    return { discordMessageId: sent.id };
}

/**
 * Phase 6 plan 06-13 — render tournament_announce + tournament_announce_update.
 *
 * Source: .planning/phases/06-tournaments-brackets/06-13-PLAN.md task 2 +
 *         06-RESEARCH.md § Open Question 5 (3 distinct kinds — LOCKED inline).
 *
 * Channel resolution: row.channel_id is server-side resolved at outbound write
 * time by TournamentObserver (plan 06-10). For Phase 6 v1 the column may be
 * empty (the observer writes '' as a placeholder per plan 06-10 — the bot
 * worker resolves the channel via env.DISCORD_ANNOUNCE_CHANNEL_ID OR the
 * organising clan's announce channel). For tests we exercise the embed
 * builder directly; the channel resolution lives in env.* and is integration-
 * tested end-to-end in plan 06-14.
 *
 * Update path: tournament_announce_update has the SAME payload shape as
 * tournament_announce but the bot worker treats it as "post a fresh message"
 * since Phase 6 v1 does NOT edit prior tournament messages — the lifecycle
 * status flip emits a NEW announce row rather than editing the previous one
 * (this differs from match_announce_update which DOES edit, by design — the
 * tournament-side conversation is more verbose and individual status flips
 * deserve their own messages).
 */
async function renderTournamentAnnounce(
    client: Client,
    row: OutboundRow,
): Promise<RenderResult> {
    const payload = row.payload as TournamentAnnouncePayload;

    const channel = await client.channels.fetch(row.channel_id);
    if (
        channel === null ||
        !channel.isTextBased() ||
        !('send' in channel)
    ) {
        throw new Error(
            `[bot/render] Channel ${row.channel_id} not found or not text-based`,
        );
    }
    const sendable = channel as TextBasedChannel & {
        send: (opts: unknown) => Promise<Message>;
    };

    const embed = buildTournamentAnnounceEmbed(payload);

    // T-05-10-07 / T-05-11-05: allowed_mentions:{parse:[]} suppresses
    // @everyone / @here / role / user mention parsing regardless of what the
    // embed title or description contains.
    const sent = await sendable.send({
        embeds: [embed],
        allowed_mentions: { parse: [] as never[] },
    });
    return { discordMessageId: sent.id };
}

/**
 * Phase 6 plan 06-13 — render bracket_result_announce.
 *
 * Source: .planning/phases/06-tournaments-brackets/06-13-PLAN.md task 2 +
 *         06-08-PLAN.md (BracketAdvancementService outbound enqueue).
 *
 * Fires once per resolved bracket — when a tournament match records a winner
 * and BracketAdvancementService propagates that winner into the bracket tree,
 * an outbound row lands with this kind. Channel resolution is server-side per
 * T-06-13-04 (organising clan's announce channel OR system announce channel).
 */
async function renderBracketResultAnnounce(
    client: Client,
    row: OutboundRow,
): Promise<RenderResult> {
    const payload = row.payload as BracketResultPayload;

    const channel = await client.channels.fetch(row.channel_id);
    if (
        channel === null ||
        !channel.isTextBased() ||
        !('send' in channel)
    ) {
        throw new Error(
            `[bot/render] Channel ${row.channel_id} not found or not text-based`,
        );
    }
    const sendable = channel as TextBasedChannel & {
        send: (opts: unknown) => Promise<Message>;
    };

    const embed = buildBracketResultEmbed(payload);

    const sent = await sendable.send({
        embeds: [embed],
        allowed_mentions: { parse: [] as never[] },
    });
    return { discordMessageId: sent.id };
}

async function renderRoleSync(
    client: Client,
    row: OutboundRow,
): Promise<RenderResult> {
    const payload = row.payload as RoleSyncPayload;
    const userId = payload.discord_user_id;
    const roleId = payload.discord_role_id;
    const action = payload.action;

    if (
        typeof userId !== 'string' ||
        userId === '' ||
        typeof roleId !== 'string' ||
        roleId === ''
    ) {
        throw new Error(
            `[bot/render] role_sync payload missing discord_user_id or discord_role_id (row ${row.id})`,
        );
    }
    if (action !== 'add' && action !== 'remove') {
        throw new Error(
            `[bot/render] role_sync payload has invalid action='${String(action)}' (row ${row.id})`,
        );
    }

    const guild = await client.guilds.fetch(env.DISCORD_GUILD_ID);
    const member = await guild.members.fetch(userId).catch(() => null);
    if (member === null) {
        throw new Error(
            `[bot/render] Guild member ${userId} not found in guild ${env.DISCORD_GUILD_ID}`,
        );
    }

    if (action === 'add') {
        await member.roles.add(roleId);
    } else {
        await member.roles.remove(roleId);
    }

    // Role sync has no Discord message id; emit a synthetic identifier so
    // the web side's POST /outbound-messages/{id}/sent ack (which requires
    // a non-empty sent_message_id by validation) succeeds. The web side
    // never reads this back for role_sync rows.
    return {
        discordMessageId: `role_sync:${action}:${userId}:${roleId}`,
    };
}
