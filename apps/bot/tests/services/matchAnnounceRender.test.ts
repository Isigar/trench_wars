// Trenchwars bot — renderMatchAnnounce dispatch-path tests.
//
// Why this file exists: the production match_announce path (render.ts
// renderMatchAnnounce) re-fetches the match DTO from GET /api/bot/matches/{id}
// and posts a matchCard embed to the announce channel. Until now NO test
// exercised the real renderMatchAnnounce — outbound.test.ts mocks render()
// wholesale, and auditHotfixRender.test.ts / tournamentAnnounceRender.test.ts
// explicitly skip the match_announce branch. That blind spot hid a runtime
// bug: BotApiMatchController::show() wraps the DTO in a { data } envelope, but
// renderMatchAnnounce read it bare (`api.get<PublicMatchData>`), so matchCard
// received the envelope object and rendered "Match undefined" with an undefined
// status field. These tests pin the unwrap so the bug cannot regress.
//
// Harness idiom mirrors services/auditHotfixRender.test.ts: vi.mock the env
// module (no real secrets at module load), vi.mock the api singleton, and build
// a minimal fake Client exposing channels.fetch + send.

import type { Client } from 'discord.js';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

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

vi.mock('../../src/services/api.js', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
        request: vi.fn(),
    },
}));

import { render } from '../../src/services/render.js';
import { api } from '../../src/services/api.js';
import type { OutboundRow } from '../../src/types/apiContracts.js';

const MATCH_ID = '01234567-89ab-cdef-0123-456789abcdef';
const ANNOUNCE_CHANNEL = 'announce-chan-123';

interface FakeSendable {
    isTextBased: () => boolean;
    send: ReturnType<typeof vi.fn>;
    messages: { fetch: ReturnType<typeof vi.fn> };
}

function makeChannelClient(channel: FakeSendable): {
    client: Client;
    channelFetch: ReturnType<typeof vi.fn>;
} {
    const channelFetch = vi.fn(async () => channel);
    const fake = {
        channels: { fetch: channelFetch },
        users: { fetch: vi.fn() },
    };
    return { client: fake as unknown as Client, channelFetch };
}

function makeSendable(): FakeSendable {
    return {
        isTextBased: () => true,
        send: vi.fn(async () => ({ id: 'discord-msg-id' })),
        messages: { fetch: vi.fn() },
    };
}

function makeRow(overrides: Partial<OutboundRow> = {}): OutboundRow {
    return {
        id: 'row-match-announce',
        channel_id: ANNOUNCE_CHANNEL,
        message_type: 'match_announce',
        status: 'pending',
        payload: { kind: 'match_announce_new', match_id: MATCH_ID },
        attempts: 0,
        last_error: null,
        sent_message_id: null,
        causer_user_id: null,
        backoff_until: null,
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
        ...overrides,
    };
}

// The shape BotApiMatchController::show() actually returns: the scalar
// PublicMatchData projection wrapped in a { data } envelope.
function matchEnvelope(overrides: Record<string, unknown> = {}): {
    data: Record<string, unknown>;
} {
    return {
        data: {
            id: MATCH_ID,
            title: { en: 'Scrim Alpha' },
            description: null,
            status: 'open',
            scheduled_at: null,
            host_clan_id: null,
            game_match_type_id: null,
            ...overrides,
        },
    };
}

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    vi.clearAllMocks();
});

describe('renderMatchAnnounce (real dispatch path)', () => {
    it('re-fetches GET /matches/{id} and posts a card built from the unwrapped { data } envelope', async () => {
        vi.mocked(api.get).mockResolvedValue(matchEnvelope());
        const channel = makeSendable();
        const { client, channelFetch } = makeChannelClient(channel);

        const result = await render(client, makeRow());

        expect(api.get).toHaveBeenCalledWith(`/matches/${MATCH_ID}`);
        expect(channelFetch).toHaveBeenCalledWith(ANNOUNCE_CHANNEL);
        expect(channel.send).toHaveBeenCalledTimes(1);
        expect(result).toEqual({ discordMessageId: 'discord-msg-id' });

        // The embed must reflect the real match — NOT "Match undefined". If the
        // envelope were read bare, m.title?.en is undefined → title falls back
        // to `Match undefined` and the status field value is undefined.
        const sent = channel.send.mock.calls[0]?.[0] as {
            embeds: { data: {
                title?: string;
                footer?: { text: string };
                fields?: { name: string; value: string }[];
            } }[];
        };
        const embed = sent.embeds[0].data;
        expect(embed.title).toBe('Scrim Alpha');
        expect(embed.footer?.text).toBe(`Match id: ${MATCH_ID}`);
        const statusField = embed.fields?.find((f) => f.name === 'Status');
        expect(statusField?.value).toBe('open');
        expect(JSON.stringify(embed)).not.toContain('undefined');
    });

    it('falls back to `Match <id>` (never `Match undefined`) when title.en is absent', async () => {
        vi.mocked(api.get).mockResolvedValue(matchEnvelope({ title: null }));
        const channel = makeSendable();
        const { client } = makeChannelClient(channel);

        await render(client, makeRow());

        const sent = channel.send.mock.calls[0]?.[0] as {
            embeds: { data: { title?: string } }[];
        };
        expect(sent.embeds[0].data.title).toBe(`Match ${MATCH_ID}`);
    });
});
