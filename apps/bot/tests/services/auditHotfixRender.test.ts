// Trenchwars bot — v1.0 milestone-audit hotfix dispatcher tests.
//
// Source: .planning/v1.0-MILESTONE-AUDIT.md BLOCKER 1 +
//         .planning/audit-hotfix-bot-dispatcher-SUMMARY.md.
//
// Asserts the 3 new render.ts branches:
//   - renderArticleAnnounce  (Phase 7 article_announce)
//   - renderMatchResultAnnounce (Phase 8 match_result_announce)
//   - renderUserDm           (Phase 9 user_dm via client.users.fetch)
//
// AND the channel fallback path (env.DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID used
// when row.channel_id === '').
//
// Idiom matches services/outbound.test.ts — vi.mock the env module so the
// real secret-required env.ts doesn't trip at module load, then build a
// minimal fake Client that exposes the methods the dispatcher reaches into
// (channels.fetch + send for channel kinds, users.fetch + send for user_dm).

import type { Client } from 'discord.js';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Mock env BEFORE the SUT import so DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID is
// available without requiring real Discord secrets at test time.
vi.mock('../../src/env.js', () => ({
    env: {
        DISCORD_BOT_TOKEN: 'test-token',
        DISCORD_APPLICATION_ID: 'test-app',
        DISCORD_GUILD_ID: 'test-guild',
        WEB_API_URL: 'http://test',
        WEB_API_TOKEN: 'test-api-token',
        OUTBOUND_POLL_INTERVAL_MS: 5000,
        DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID: 'fallback-channel-id',
    },
}));

// Neutralise the api singleton — render.ts only reaches it from the
// match_announce branch, which these tests don't exercise.
vi.mock('../../src/services/api.js', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
        request: vi.fn(),
    },
}));

import { render } from '../../src/services/render.js';
import type { OutboundRow } from '../../src/types/apiContracts.js';

// ---------------------------------------------------------------------------
// Fake Discord client builders. Keep the surface tiny — render.ts only uses
// client.channels.fetch + sendable.send for channel kinds and
// client.users.fetch + user.send for user_dm.
// ---------------------------------------------------------------------------

interface FakeSendable {
    isTextBased: () => boolean;
    send: ReturnType<typeof vi.fn>;
}

interface FakeChannelsFetch {
    fetch: ReturnType<typeof vi.fn>;
}

interface FakeUsersFetch {
    fetch: ReturnType<typeof vi.fn>;
}

interface FakeClient {
    channels: FakeChannelsFetch;
    users: FakeUsersFetch;
}

function makeChannelClient(opts: {
    channel: FakeSendable | null;
    expectedFetchId?: string;
}): { client: Client; channelFetch: ReturnType<typeof vi.fn> } {
    const channelFetch = vi.fn(async (id: string) => {
        if (
            opts.expectedFetchId !== undefined &&
            id !== opts.expectedFetchId
        ) {
            // Surface the mismatch in test output rather than silently
            // returning the channel.
            throw new Error(
                `channels.fetch expected ${opts.expectedFetchId}, got ${id}`,
            );
        }
        return opts.channel;
    });

    const fake: FakeClient = {
        channels: { fetch: channelFetch },
        users: { fetch: vi.fn() },
    };
    return { client: fake as unknown as Client, channelFetch };
}

function makeUserClient(opts: {
    user: { send: ReturnType<typeof vi.fn> } | null;
}): { client: Client; userFetch: ReturnType<typeof vi.fn> } {
    const userFetch = vi.fn(async () => opts.user);
    const fake: FakeClient = {
        channels: { fetch: vi.fn() },
        users: { fetch: userFetch },
    };
    return { client: fake as unknown as Client, userFetch };
}

function makeRow(overrides: Partial<OutboundRow>): OutboundRow {
    return {
        id: 'row-test',
        channel_id: 'channel-from-row',
        message_type: 'article_announce',
        status: 'pending',
        payload: {},
        attempts: 0,
        last_error: null,
        sent_message_id: null,
        causer_user_id: null,
        backoff_until: null,
        created_at: '2026-05-17T00:00:00Z',
        updated_at: '2026-05-17T00:00:00Z',
        ...overrides,
    };
}

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    vi.clearAllMocks();
});

// ---------------------------------------------------------------------------
// render() top-level dispatch — guarantee the 3 new kinds are recognised.
// Without this assertion the audit's "Unknown message_type" defect could
// regress silently.
// ---------------------------------------------------------------------------

describe('render() recognises the 3 v1.0 audit hotfix kinds', () => {
    it('does NOT throw Unknown message_type for article_announce', async () => {
        const sendable: FakeSendable = {
            isTextBased: () => true,
            send: vi.fn().mockResolvedValue({ id: 'discord-msg-art' }),
        };
        const { client } = makeChannelClient({ channel: sendable });

        const row = makeRow({
            id: 'art-1',
            message_type: 'article_announce',
            channel_id: 'channel-A',
            payload: {
                kind: 'article_announce',
                article_id: 'art-1',
                article_slug: 'launch',
                embeds: [{ title: 'Launch', description: 'body' }],
            },
        });

        await expect(render(client, row)).resolves.toEqual({
            discordMessageId: 'discord-msg-art',
        });
    });

    it('does NOT throw Unknown message_type for match_result_announce', async () => {
        const sendable: FakeSendable = {
            isTextBased: () => true,
            send: vi.fn().mockResolvedValue({ id: 'discord-msg-mr' }),
        };
        const { client } = makeChannelClient({ channel: sendable });

        const row = makeRow({
            id: 'mr-1',
            message_type: 'match_result_announce',
            channel_id: 'channel-B',
            payload: {
                kind: 'match_result_announce',
                match_id: 'mr-1',
                allies_score: 3,
                axis_score: 2,
                winner_clan_name: 'Wolves',
                mvps: [],
                embeds: [{ title: 'Match result', fields: [] }],
            },
        });

        await expect(render(client, row)).resolves.toEqual({
            discordMessageId: 'discord-msg-mr',
        });
    });

    it('does NOT throw Unknown message_type for user_dm', async () => {
        const send = vi.fn().mockResolvedValue({ id: 'discord-msg-dm' });
        const { client } = makeUserClient({ user: { send } });

        const row = makeRow({
            id: 'dm-1',
            message_type: 'user_dm',
            channel_id: '',
            payload: {
                recipient_id: '12345678901234567',
                embed_title: 'Ping',
                embed_description: 'Body',
                cta_url: 'https://example.test/m/1',
                color_token: 'info',
            },
        });

        await expect(render(client, row)).resolves.toEqual({
            discordMessageId: 'discord-msg-dm',
        });
    });
});

// ---------------------------------------------------------------------------
// renderArticleAnnounce — Phase 7
// ---------------------------------------------------------------------------

describe('renderArticleAnnounce', () => {
    it('posts to row.channel_id when set, with allowed_mentions:parse:[] guard', async () => {
        const send = vi.fn().mockResolvedValue({ id: 'msg-art' });
        const { client, channelFetch } = makeChannelClient({
            channel: { isTextBased: () => true, send },
            expectedFetchId: 'channel-A',
        });

        const row = makeRow({
            message_type: 'article_announce',
            channel_id: 'channel-A',
            payload: {
                kind: 'article_announce',
                article_id: 'a-1',
                article_slug: 'slug',
                embeds: [{ title: 'T', description: 'D' }],
            },
        });

        await render(client, row);

        expect(channelFetch).toHaveBeenCalledWith('channel-A');
        expect(send).toHaveBeenCalledTimes(1);
        const sendArg = send.mock.calls[0]![0] as {
            embeds: unknown[];
            allowed_mentions: { parse: never[] };
        };
        expect(Array.isArray(sendArg.embeds)).toBe(true);
        expect(sendArg.embeds).toHaveLength(1);
        expect(sendArg.allowed_mentions).toEqual({ parse: [] });
    });

    it('falls back to env.DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID when row.channel_id is empty', async () => {
        const send = vi.fn().mockResolvedValue({ id: 'msg-fb' });
        const { client, channelFetch } = makeChannelClient({
            channel: { isTextBased: () => true, send },
            expectedFetchId: 'fallback-channel-id',
        });

        const row = makeRow({
            message_type: 'article_announce',
            channel_id: '',
            payload: {
                kind: 'article_announce',
                article_id: 'a-2',
                article_slug: 's2',
                embeds: [{ title: 'T' }],
            },
        });

        await render(client, row);
        expect(channelFetch).toHaveBeenCalledWith('fallback-channel-id');
    });

    it('throws when channel.fetch returns null', async () => {
        const { client } = makeChannelClient({ channel: null });

        const row = makeRow({
            message_type: 'article_announce',
            channel_id: 'channel-missing',
            payload: {
                kind: 'article_announce',
                article_id: 'a-3',
                article_slug: 's3',
                embeds: [{ title: 'T' }],
            },
        });

        await expect(render(client, row)).rejects.toThrow(
            /Channel channel-missing not found/,
        );
    });

    it('throws when channel is not text-based', async () => {
        const { client } = makeChannelClient({
            channel: { isTextBased: () => false, send: vi.fn() },
        });

        const row = makeRow({
            message_type: 'article_announce',
            channel_id: 'voice-channel',
            payload: {
                kind: 'article_announce',
                article_id: 'a-4',
                article_slug: 's4',
                embeds: [{ title: 'T' }],
            },
        });

        await expect(render(client, row)).rejects.toThrow(/not text-based/);
    });
});

// ---------------------------------------------------------------------------
// renderMatchResultAnnounce — Phase 8
// ---------------------------------------------------------------------------

describe('renderMatchResultAnnounce', () => {
    it('posts with the pre-shaped embed and allowed_mentions guard', async () => {
        const send = vi.fn().mockResolvedValue({ id: 'msg-mr' });
        const { client } = makeChannelClient({
            channel: { isTextBased: () => true, send },
        });

        const row = makeRow({
            message_type: 'match_result_announce',
            channel_id: 'clan-announce-chan',
            payload: {
                kind: 'match_result_announce',
                match_id: 'mr-7',
                allies_score: 4,
                axis_score: 0,
                winner_clan_name: 'Wolves',
                mvps: [],
                embeds: [
                    {
                        title: 'Match result',
                        color: 0x9b2c3d,
                        fields: [
                            { name: 'Score', value: '4 - 0', inline: true },
                        ],
                    },
                ],
            },
        });

        const result = await render(client, row);

        expect(result.discordMessageId).toBe('msg-mr');
        expect(send).toHaveBeenCalledTimes(1);
        const sendArg = send.mock.calls[0]![0] as {
            embeds: unknown[];
            allowed_mentions: { parse: never[] };
        };
        expect(sendArg.embeds).toHaveLength(1);
        expect(sendArg.allowed_mentions).toEqual({ parse: [] });
    });

    it('uses host-clan channel_id verbatim (no fallback when row.channel_id is set)', async () => {
        const send = vi.fn().mockResolvedValue({ id: 'msg-mr' });
        const { client, channelFetch } = makeChannelClient({
            channel: { isTextBased: () => true, send },
            expectedFetchId: 'clan-channel-xyz',
        });

        const row = makeRow({
            message_type: 'match_result_announce',
            channel_id: 'clan-channel-xyz',
            payload: {
                kind: 'match_result_announce',
                match_id: 'mr-8',
                allies_score: null,
                axis_score: null,
                winner_clan_name: null,
                mvps: [],
                embeds: [{ title: 'Match result', fields: [] }],
            },
        });

        await render(client, row);
        expect(channelFetch).toHaveBeenCalledWith('clan-channel-xyz');
        // fallback env channel id must NOT have been queried.
        expect(channelFetch).not.toHaveBeenCalledWith('fallback-channel-id');
    });
});

// ---------------------------------------------------------------------------
// renderUserDm — Phase 9 (DM via client.users.fetch + user.send)
// ---------------------------------------------------------------------------

describe('renderUserDm', () => {
    it('fetches the user by recipient_id and sends via user.send()', async () => {
        const send = vi.fn().mockResolvedValue({ id: 'dm-msg' });
        const { client, userFetch } = makeUserClient({ user: { send } });

        const row = makeRow({
            message_type: 'user_dm',
            channel_id: '',
            payload: {
                recipient_id: '99887766554433221',
                embed_title: 'Title',
                embed_description: 'Body',
                cta_url: 'https://example.test/m/1',
                color_token: 'info',
            },
        });

        const result = await render(client, row);

        expect(userFetch).toHaveBeenCalledWith('99887766554433221');
        expect(send).toHaveBeenCalledTimes(1);
        const sendArg = send.mock.calls[0]![0] as {
            embeds: unknown[];
            allowed_mentions: { parse: never[] };
        };
        expect(sendArg.embeds).toHaveLength(1);
        expect(sendArg.allowed_mentions).toEqual({ parse: [] });
        expect(result.discordMessageId).toBe('dm-msg');
    });

    it('rejects with a descriptive error when recipient_id is missing from payload', async () => {
        const { client } = makeUserClient({ user: { send: vi.fn() } });

        const row = makeRow({
            id: 'no-recipient',
            message_type: 'user_dm',
            channel_id: '',
            payload: {
                // recipient_id absent
                embed_title: 'Title',
            },
        });

        await expect(render(client, row)).rejects.toThrow(
            /user_dm payload missing recipient_id/,
        );
    });

    it('rejects with the Discord error when user.send() rejects (50007 DMs disabled)', async () => {
        const send = vi.fn().mockRejectedValue(
            new Error('DiscordAPIError[50007]: Cannot send messages to this user'),
        );
        const { client } = makeUserClient({ user: { send } });

        const row = makeRow({
            message_type: 'user_dm',
            channel_id: '',
            payload: {
                recipient_id: '11122233344455566',
                embed_title: 'Title',
            },
        });

        await expect(render(client, row)).rejects.toThrow(/50007/);
        // Importantly, the worker (services/outbound.ts) catches this and
        // marks the row failed with the Discord error string — proven by the
        // existing outbound.test.ts "marks failed with err.message" case.
    });

    it('does NOT consult env.DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID for user_dm (DMs bypass channels)', async () => {
        const send = vi.fn().mockResolvedValue({ id: 'dm-msg' });
        const userFetch = vi.fn(async () => ({ send }));
        // channels.fetch should NEVER be called for a user_dm row — wire a
        // spy that throws if it is.
        const channelsFetch = vi.fn(async () => {
            throw new Error(
                'channels.fetch must NOT be called for user_dm rows',
            );
        });
        const fake: FakeClient = {
            channels: { fetch: channelsFetch },
            users: { fetch: userFetch },
        };
        const client = fake as unknown as Client;

        const row = makeRow({
            message_type: 'user_dm',
            channel_id: '',
            payload: {
                recipient_id: '55544433322211100',
                embed_title: 'Hello',
            },
        });

        await render(client, row);
        expect(channelsFetch).not.toHaveBeenCalled();
        expect(userFetch).toHaveBeenCalledWith('55544433322211100');
    });
});

// ---------------------------------------------------------------------------
// resolveChannelId fallback — both row.channel_id and env empty must fail
// fast so the operator sees a clear error message in the Filament resource.
// ---------------------------------------------------------------------------

describe('resolveChannelId fallback failure surface', () => {
    it('article_announce: throws when both row.channel_id and env fallback are empty', async () => {
        // Override env in-place for this single test — Vitest's vi.mock
        // hoists the factory once at file scope, so we can't re-mock per-test
        // without resetModules. Instead, test the codepath by importing the
        // env mock object and temporarily replacing the field via Object
        // mutation (the env constant is exported as `as const` but the
        // mocked module object is writable).
        const envModule = await import('../../src/env.js');
        const original = envModule.env.DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID;
        // The mocked object permits mutation because vi.mock factory returns
        // a plain object — typecast away const-ness for the test only.
        (
            envModule.env as unknown as {
                DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID: string;
            }
        ).DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID = '';
        try {
            const { client } = makeChannelClient({ channel: null });

            const row = makeRow({
                id: 'no-channel',
                message_type: 'article_announce',
                channel_id: '',
                payload: {
                    kind: 'article_announce',
                    article_id: 'a-9',
                    article_slug: 's9',
                    embeds: [{ title: 'T' }],
                },
            });

            await expect(render(client, row)).rejects.toThrow(
                /No channel resolvable for row no-channel/,
            );
        } finally {
            // Restore so subsequent tests in the file see the original env
            // (test isolation — Vitest does NOT reset module state between
            // tests by default).
            (
                envModule.env as unknown as {
                    DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID: string;
                }
            ).DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID = original;
        }
    });
});
