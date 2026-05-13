// Trenchwars bot — guildMemberUpdate event handler.
//
// Source: .planning/phases/05-discord-bot-v1/05-11-PLAN.md task 2 (Wave 10).
// RESEARCH §Pattern 6 verbatim. SC-4 (reconciliation second half): when a
// server admin manually adds or removes a clan role on a Discord member
// (bypassing the website Filament admin), the bot diffs the old/new role
// caches and POSTs each delta to /api/bot/discord-events/role-change. The
// web side's BotApiDiscordEventController (plan 05-04) either reconciles
// the ClanMembership table OR silently no-ops if the event is recognised
// as an echo of a recent bot-initiated role_sync (Pitfall 10, 60s window).
//
// Why both directions:
//   - Web -> Discord — handled by SyncDiscordRolesJob (plan 05-06)
//     writes role_sync outbound rows; this bot's outbound worker
//     (plan 05-11 outbound.ts) drains them.
//   - Discord -> Web — handled by THIS file: the bot observes raw
//     Discord events and reports each role delta back to the web.
//
// Pitfall 10 echo suppression is WEB-SIDE. The bot fires events freely;
// the BotApiDiscordEventController checks
//   discord_outbound_messages WHERE message_type='role_sync'
//                                AND payload->discord_user_id = :u
//                                AND payload->discord_role_id = :r
//                                AND status='sent'
//                                AND sent_at > NOW() - 60s
// before applying the change. The bot does NOT need its own ignore-list.
//
// Partials: oldMember may be a PartialGuildMember when the GuildMember
// cache hasn't seen this user before. In that case oldMember.roles.cache
// is still populated (discord.js fetches role refs from the partial
// member object), but it may not reflect the FULL prior state if the
// gateway dropped role-update events while the bot was offline. The web
// side reconciler is idempotent (Pattern 6 / plan 05-04) — a missed
// delta is recoverable on the next ClanMembership write, or by an
// operator-driven full sync.
//
// Testability: the per-event diff logic is extracted into the public
// `handleGuildMemberUpdate(oldMember, newMember)` helper. The
// `registerGuildMemberUpdateHandler(client)` function just wires the
// helper into `client.on(Events.GuildMemberUpdate, ...)`. Unit tests
// invoke the helper directly without needing a mock client.

import {
    type Client,
    Events,
    type GuildMember,
    type PartialGuildMember,
} from 'discord.js';

import { api } from '../services/api.js';

/**
 * handleGuildMemberUpdate — diff old/new role caches + report each delta.
 *
 * Exported for unit testing. The outer try/catch is in
 * registerGuildMemberUpdateHandler so a thrown error here surfaces in
 * tests via assertion rather than being swallowed.
 */
export async function handleGuildMemberUpdate(
    oldMember: GuildMember | PartialGuildMember,
    newMember: GuildMember,
): Promise<void> {
    const removed = oldMember.roles.cache.filter(
        (r) => !newMember.roles.cache.has(r.id),
    );
    const added = newMember.roles.cache.filter(
        (r) => !oldMember.roles.cache.has(r.id),
    );

    for (const [, role] of removed) {
        await api.post('/discord-events/role-change', {
            user_discord_id: newMember.id,
            role_discord_id: role.id,
            action: 'remove',
        });
    }
    for (const [, role] of added) {
        await api.post('/discord-events/role-change', {
            user_discord_id: newMember.id,
            role_discord_id: role.id,
            action: 'add',
        });
    }
}

export function registerGuildMemberUpdateHandler(client: Client): void {
    client.on(Events.GuildMemberUpdate, async (oldMember, newMember) => {
        try {
            await handleGuildMemberUpdate(oldMember, newMember);
        } catch (err) {
            // Log and swallow — one failed delta must not crash the
            // gateway listener. The web side reconciler is idempotent
            // and a missed event can be recovered by the next role
            // change on the same membership.
            console.error('[bot/guildMemberUpdate]', err);
        }
    });
}
