// Trenchwars bot — tournament/bracket announce channel-resolution tests.
//
// Source: production-readiness fix [B2] — renderTournamentAnnounce +
// renderBracketResultAnnounce previously called client.channels.fetch with the
// raw (and, for these kinds, always-empty) row.channel_id, so every tournament
// + bracket announce failed. They now go through resolveChannelId(row), which
// falls back to env.DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID and throws a clear error
// when both the row column and the env var are empty.
//
// Idiom mirrors services/auditHotfixRender.test.ts — vi.mock the env module so
// the secret-required env.ts doesn't trip at module load, then build a minimal
// fake Client exposing channels.fetch + send.

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

// Neutralise the api singleton — the tournament/bracket branches never reach
// it (only the match_announce branch does).
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
// Fake Discord client — render.ts only uses client.channels.fetch +
// sendable.send for these kinds.
// ---------------------------------------------------------------------------

interface FakeSendable {
    isTextBased: () => boolean;
    send: ReturnType<typeof vi.fn>;
}

interface FakeClient {
    channels: { fetch: ReturnType<typeof vi.fn> };
    users: { fetch: ReturnType<typeof vi.fn> };
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

function makeRow(overrides: Partial<OutboundRow>): OutboundRow {
    return {
        id: 'row-test',
        channel_id: '',
        message_type: 'tournament_announce',
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
// renderTournamentAnnounce — channel resolution
// ---------------------------------------------------------------------------

describe('renderTournamentAnnounce channel resolution', () => {
    it('falls back to env.DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID when row.channel_id is empty', async () => {
        const send = vi.fn().mockResolvedValue({ id: 'msg-tourney' });
        const { client, channelFetch } = makeChannelClient({
            channel: { isTextBased: () => true, send },
            expectedFetchId: 'fallback-channel-id',
        });

        const row = makeRow({
            message_type: 'tournament_announce',
            channel_id: '',
            payload: {
                kind: 'tournament_announce',
                tournament_id: 't-1',
                tournament_slug: 'spring-cup',
                title: { en: 'Spring Cup' },
                format: 'single_elim',
                status: 'open',
                starts_at: null,
                ends_at: null,
                organiser_user_id: null,
                max_participants: 16,
                is_public: true,
            },
        });

        const result = await render(client, row);

        expect(channelFetch).toHaveBeenCalledWith('fallback-channel-id');
        expect(result.discordMessageId).toBe('msg-tourney');
        const sendArg = send.mock.calls[0]![0] as {
            embeds: unknown[];
            allowed_mentions: { parse: never[] };
        };
        expect(sendArg.embeds).toHaveLength(1);
        expect(sendArg.allowed_mentions).toEqual({ parse: [] });
    });

    it('uses row.channel_id verbatim when set (no fallback)', async () => {
        const send = vi.fn().mockResolvedValue({ id: 'msg-tourney2' });
        const { client, channelFetch } = makeChannelClient({
            channel: { isTextBased: () => true, send },
            expectedFetchId: 'explicit-channel',
        });

        const row = makeRow({
            message_type: 'tournament_announce_update',
            channel_id: 'explicit-channel',
            payload: {
                kind: 'tournament_announce_update',
                tournament_id: 't-2',
                tournament_slug: 'autumn-cup',
                title: { en: 'Autumn Cup' },
                format: 'double_elim',
                status: 'running',
                starts_at: null,
                ends_at: null,
                organiser_user_id: null,
                max_participants: null,
                is_public: true,
            },
        });

        await render(client, row);
        expect(channelFetch).toHaveBeenCalledWith('explicit-channel');
        expect(channelFetch).not.toHaveBeenCalledWith('fallback-channel-id');
    });
});

// ---------------------------------------------------------------------------
// renderBracketResultAnnounce — channel resolution
// ---------------------------------------------------------------------------

describe('renderBracketResultAnnounce channel resolution', () => {
    it('falls back to env.DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID when row.channel_id is empty', async () => {
        const send = vi.fn().mockResolvedValue({ id: 'msg-bracket' });
        const { client, channelFetch } = makeChannelClient({
            channel: { isTextBased: () => true, send },
            expectedFetchId: 'fallback-channel-id',
        });

        const row = makeRow({
            message_type: 'bracket_result_announce',
            channel_id: '',
            payload: {
                kind: 'bracket_result_announce',
                tournament_id: 't-3',
                tournament_slug: 'spring-cup',
                tournament_title: 'Spring Cup',
                stage_id: 's-1',
                stage_type: 'single_elim',
                bracket_id: 'b-1',
                round_number: 1,
                position: 1,
                winner_participant_id: 'p-1',
                winner_clan_id: 'c-1',
                winner_clan_name: 'Wolves',
                participant_a_clan_name: 'Wolves',
                participant_b_clan_name: 'Bears',
            },
        });

        const result = await render(client, row);

        expect(channelFetch).toHaveBeenCalledWith('fallback-channel-id');
        expect(result.discordMessageId).toBe('msg-bracket');
        const sendArg = send.mock.calls[0]![0] as {
            embeds: unknown[];
            allowed_mentions: { parse: never[] };
        };
        expect(sendArg.embeds).toHaveLength(1);
        expect(sendArg.allowed_mentions).toEqual({ parse: [] });
    });
});

// ---------------------------------------------------------------------------
// resolveChannelId fallback failure — both row.channel_id and env empty must
// fail fast so the operator sees a clear error in the Filament resource.
// ---------------------------------------------------------------------------

describe('resolveChannelId fallback failure surface (tournament/bracket)', () => {
    it('tournament_announce: throws when both row.channel_id and env fallback are empty', async () => {
        const envModule = await import('../../src/env.js');
        const original = envModule.env.DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID;
        (
            envModule.env as unknown as {
                DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID: string;
            }
        ).DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID = '';
        try {
            const { client } = makeChannelClient({ channel: null });

            const row = makeRow({
                id: 'no-channel-tourney',
                message_type: 'tournament_announce',
                channel_id: '',
                payload: {
                    kind: 'tournament_announce',
                    tournament_id: 't-9',
                    tournament_slug: 's9',
                    title: { en: 'No-channel Cup' },
                    format: 'single_elim',
                    status: 'open',
                    starts_at: null,
                    ends_at: null,
                    organiser_user_id: null,
                    max_participants: null,
                    is_public: true,
                },
            });

            await expect(render(client, row)).rejects.toThrow(
                /No channel resolvable for row no-channel-tourney/,
            );
        } finally {
            (
                envModule.env as unknown as {
                    DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID: string;
                }
            ).DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID = original;
        }
    });

    it('bracket_result_announce: throws when both row.channel_id and env fallback are empty', async () => {
        const envModule = await import('../../src/env.js');
        const original = envModule.env.DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID;
        (
            envModule.env as unknown as {
                DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID: string;
            }
        ).DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID = '';
        try {
            const { client } = makeChannelClient({ channel: null });

            const row = makeRow({
                id: 'no-channel-bracket',
                message_type: 'bracket_result_announce',
                channel_id: '',
                payload: {
                    kind: 'bracket_result_announce',
                    tournament_id: 't-9',
                    tournament_slug: 's9',
                    tournament_title: 'No-channel Cup',
                    stage_id: 's-9',
                    stage_type: 'single_elim',
                    bracket_id: 'b-9',
                    round_number: 2,
                    position: 1,
                    winner_participant_id: 'p-9',
                    winner_clan_id: 'c-9',
                    winner_clan_name: 'Wolves',
                    participant_a_clan_name: 'Wolves',
                    participant_b_clan_name: 'Bears',
                },
            });

            await expect(render(client, row)).rejects.toThrow(
                /No channel resolvable for row no-channel-bracket/,
            );
        } finally {
            (
                envModule.env as unknown as {
                    DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID: string;
                }
            ).DISCORD_LEAGUE_ANNOUNCE_CHANNEL_ID = original;
        }
    });
});
