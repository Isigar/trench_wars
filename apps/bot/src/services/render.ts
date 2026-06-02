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
    buildArticleAnnounceEmbed,
    buildBracketResultEmbed,
    buildMatchResultAnnounceEmbed,
    buildTournamentAnnounceEmbed,
    buildUserDmEmbed,
    matchCard,
    type ArticleAnnouncePayload,
    type BracketResultPayload,
    type MatchResultAnnouncePayload,
    type TournamentAnnouncePayload,
    type UserDmPayload,
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
    // v1.0 milestone-audit hotfix — Phase 7/8/9 outbound kinds. See
    // .planning/v1.0-MILESTONE-AUDIT.md BLOCKER 1 +
    // .planning/audit-hotfix-bot-dispatcher-SUMMARY.md.
    if (row.message_type === 'article_announce') {
        return renderArticleAnnounce(client, row);
    }
    if (row.message_type === 'match_result_announce') {
        return renderMatchResultAnnounce(client, row);
    }
    if (row.message_type === 'user_dm') {
        return renderUserDm(client, row);
    }
    throw new Error(
        `[bot/render] Unknown message_type: ${row.message_type}`,
    );
}

/**
 * Resolve the destination channel id for an outbound row.
 *
 * The web side writes `channel_id=''` as a placeholder for league-wide announce
 * rows (ArticleObserver Phase 7; TournamentObserver Phase 6 D-06-10-E
 * precedent). The bot resolves the actual channel here from env at dispatch
 * time. When neither row.channel_id nor the env fallback are set, the row
 * cannot be delivered — surface a render-time error so the worker marks it
 * `failed` with a clear operator message instead of throwing inside
 * client.channels.fetch('').
 */
function resolveChannelId(row: OutboundRow): string {
    if (typeof row.channel_id === 'string' && row.channel_id !== '') {
        return row.channel_id;
    }
    const fallback = env.DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID;
    if (fallback !== '') {
        return fallback;
    }
    throw new Error(
        `[bot/render] No channel resolvable for row ${row.id} (row.channel_id empty AND DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID unset)`,
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
    //
    // BotApiMatchController::show() wraps the DTO in a { data } envelope (same
    // convention as the /matches list and the outbound poll); api.get() does
    // NOT unwrap, so we destructure .data here. Reading it bare hands matchCard
    // the envelope object → every field undefined → a "Match undefined" card.
    const { data: match } = await api.get<{ data: PublicMatchData }>(
        `/matches/${matchId}`,
    );

    const channelId = resolveChannelId(row);

    const channel = await client.channels.fetch(channelId);
    if (
        channel === null ||
        !channel.isTextBased() ||
        !('send' in channel)
    ) {
        throw new Error(
            `[bot/render] Channel ${channelId} not found or not text-based`,
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
 * empty (the observer writes '' as a placeholder per plan 06-10) — in that case
 * resolveChannelId(row) falls back to env.DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID and
 * throws a clear render-time error if both are empty. For tests we exercise the
 * embed builder directly; the channel resolution is integration-tested
 * end-to-end in plan 06-14.
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

    const channelId = resolveChannelId(row);

    const channel = await client.channels.fetch(channelId);
    if (
        channel === null ||
        !channel.isTextBased() ||
        !('send' in channel)
    ) {
        throw new Error(
            `[bot/render] Channel ${channelId} not found or not text-based`,
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

    const channelId = resolveChannelId(row);

    const channel = await client.channels.fetch(channelId);
    if (
        channel === null ||
        !channel.isTextBased() ||
        !('send' in channel)
    ) {
        throw new Error(
            `[bot/render] Channel ${channelId} not found or not text-based`,
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

// ---------------------------------------------------------------------------
// v1.0 milestone-audit hotfix — Phase 7/8/9 outbound kind dispatchers.
//
// Source: .planning/v1.0-MILESTONE-AUDIT.md BLOCKER 1.
//
// Each dispatcher follows the same idiom as the Phase 5/6 paths:
//   1. Resolve destination (channel for announce kinds; user for user_dm).
//   2. Build the Discord embed via the corresponding embeds.ts factory.
//   3. Send with allowed_mentions:{parse:[]} to neutralise @everyone abuse
//      (T-05-10-07 defence-in-depth — irrelevant for user_dm but harmless).
//   4. Return the Discord message id so the worker can ack the row.
//
// Failure modes:
//   - article_announce, match_result_announce: channel.fetch() throws → row
//     marked failed by the worker. resolveChannelId throws when both
//     row.channel_id and env.DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID are empty.
//   - user_dm: client.users.fetch() throws when the snowflake is invalid.
//     user.send() throws DiscordAPIError 50007 ("Cannot send messages to
//     this user") when the recipient has closed-DMs from non-friends or has
//     blocked the bot. We do NOT swallow this — it becomes the row's
//     last_error and the operator sees it in the Filament resource. The
//     web-side preference UI can then prompt the user to enable DMs.
// ---------------------------------------------------------------------------

/**
 * Phase 7 article_announce dispatcher. Reads the pre-shaped embed payload
 * written by ArticleObserver, normalises it via buildArticleAnnounceEmbed,
 * and posts to the resolved channel (row.channel_id OR env fallback).
 */
async function renderArticleAnnounce(
    client: Client,
    row: OutboundRow,
): Promise<RenderResult> {
    const payload = row.payload as ArticleAnnouncePayload;

    const channelId = resolveChannelId(row);

    const channel = await client.channels.fetch(channelId);
    if (
        channel === null ||
        !channel.isTextBased() ||
        !('send' in channel)
    ) {
        throw new Error(
            `[bot/render] Channel ${channelId} not found or not text-based`,
        );
    }
    const sendable = channel as TextBasedChannel & {
        send: (opts: unknown) => Promise<Message>;
    };

    const embed = buildArticleAnnounceEmbed(payload);

    const sent = await sendable.send({
        embeds: [embed],
        allowed_mentions: { parse: [] as never[] },
    });
    return { discordMessageId: sent.id };
}

/**
 * Phase 8 match_result_announce dispatcher. MatchResultObserver writes the
 * row with a populated channel_id (host_clan.discord_announce_channel_id) so
 * the resolveChannelId fallback typically isn't exercised — but we still go
 * through it to preserve symmetry with the other 2 hotfix kinds.
 */
async function renderMatchResultAnnounce(
    client: Client,
    row: OutboundRow,
): Promise<RenderResult> {
    const payload = row.payload as MatchResultAnnouncePayload;

    const channelId = resolveChannelId(row);

    const channel = await client.channels.fetch(channelId);
    if (
        channel === null ||
        !channel.isTextBased() ||
        !('send' in channel)
    ) {
        throw new Error(
            `[bot/render] Channel ${channelId} not found or not text-based`,
        );
    }
    const sendable = channel as TextBasedChannel & {
        send: (opts: unknown) => Promise<Message>;
    };

    const embed = buildMatchResultAnnounceEmbed(payload);

    const sent = await sendable.send({
        embeds: [embed],
        allowed_mentions: { parse: [] as never[] },
    });
    return { discordMessageId: sent.id };
}

/**
 * Phase 9 user_dm dispatcher. Unlike the channel-targeted kinds, user_dm
 * resolves a User via client.users.fetch(recipient_id) and sends via
 * user.send() — discord.js v14 auto-creates the DM channel on first call
 * (no explicit createDM step needed).
 *
 * DM delivery failures (Discord error 50007 "Cannot send messages to this
 * user", typically a recipient who has DMs from non-friends disabled or has
 * blocked the bot) bubble up as a rejected promise; the worker marks the
 * row failed with the Discord error message. Operators see the error
 * surface in the Filament DiscordOutboundMessageResource and the recipient
 * can re-enable DMs to receive future notifications.
 *
 * D-09-03-B: recipient_id lives inside payload (not on the
 * discord_outbound_messages.channel_id column). Defensive guard catches a
 * row written without it before we make the API call.
 */
async function renderUserDm(
    client: Client,
    row: OutboundRow,
): Promise<RenderResult> {
    const payload = row.payload as UserDmPayload;

    const recipientId = payload.recipient_id;
    if (typeof recipientId !== 'string' || recipientId === '') {
        throw new Error(
            `[bot/render] user_dm payload missing recipient_id (row ${row.id})`,
        );
    }

    const fetched = await client.users.fetch(recipientId);
    if (fetched === null) {
        throw new Error(
            `[bot/render] user_dm recipient ${recipientId} not found (row ${row.id})`,
        );
    }

    const embed = buildUserDmEmbed(payload);

    // user.send() returns a Message; on DM-disabled (50007) it rejects with
    // a DiscordAPIError that bubbles to the worker's per-row catch. We
    // deliberately don't swallow that — the row should be marked failed so
    // the operator can see it. Future polish (out of scope for this hotfix)
    // could write a back-channel preference flag so the dispatcher stops
    // enqueueing rows for users who have repeatedly bounced.
    //
    // Cast through the same `(opts: unknown) => Promise<Message>` shape used
    // by the channel-side dispatchers so the snake_case `allowed_mentions`
    // (Discord REST wire format) typechecks. discord.js auto-creates the DM
    // channel on first send().
    const sendableUser = fetched as { send: (opts: unknown) => Promise<Message> };
    const sent = await sendableUser.send({
        embeds: [embed],
        allowed_mentions: { parse: [] as never[] },
    });
    return { discordMessageId: sent.id };
}
