// Trenchwars bot — /match slash command tests (Wave 7 GREEN flip).
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 3.
// Updated in Phase 12-03: /match list pagination render tests (BOT-01).
// Asserts SC-1:
//   - 4 subcommands declared on the SlashCommandBuilder data (list/info/signup/leave)
//   - non-modal branches defer reply ephemeral as the FIRST awaited statement
//     (Pitfall 1 — 3s window mitigation)
//   - signup branch invokes showModal as the INITIAL response (Pitfall 1 corollary)
//   - api.get/post/delete called with correct path + actsAsDiscordId header
//   - /match list fetches /matches?page=1 (default) from { data, meta } envelope
//   - pagination buttons + "Page X of Y" emitted only when meta.last_page > 1
//   - no pagination components when empty or single-page

import { ChatInputCommandInteraction, MessageFlags } from 'discord.js';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('../../src/services/api.js', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
        request: vi.fn(),
    },
}));

import { data, execute } from '../../src/commands/match.js';
import { api } from '../../src/services/api.js';

const INVOKER_DISCORD_ID = '100000000000000001';
const MATCH_UUID = '01234567-89ab-cdef-0123-456789abcdef';
const ROLE_UUID = 'fedcba98-7654-3210-fedc-ba9876543210';

type MockInteraction = {
    user: { id: string };
    options: {
        getSubcommand: ReturnType<typeof vi.fn>;
        getString: ReturnType<typeof vi.fn>;
    };
    deferReply: ReturnType<typeof vi.fn>;
    editReply: ReturnType<typeof vi.fn>;
    showModal: ReturnType<typeof vi.fn>;
    reply: ReturnType<typeof vi.fn>;
    isRepliable: () => boolean;
    replied: boolean;
    deferred: boolean;
};

function makeInteraction(sub: string, opts: Record<string, string> = {}): MockInteraction {
    return {
        user: { id: INVOKER_DISCORD_ID },
        options: {
            getSubcommand: vi.fn().mockReturnValue(sub),
            getString: vi.fn().mockImplementation((name: string) => opts[name]),
        },
        deferReply: vi.fn().mockResolvedValue(undefined),
        editReply: vi.fn().mockResolvedValue(undefined),
        showModal: vi.fn().mockResolvedValue(undefined),
        reply: vi.fn().mockResolvedValue(undefined),
        isRepliable: () => true,
        replied: false,
        deferred: false,
    };
}

// Minimal match stub for list responses.
const MATCH_STUB = {
    id: MATCH_UUID,
    status: 'open',
    scheduled_at: null,
    host_clan_id: null,
    title: null,
    description: null,
    game_match_type_id: null,
};

// Helpers to build paginated API responses.
function singlePageResponse() {
    return {
        data: [MATCH_STUB],
        meta: { current_page: 1, per_page: 25, total: 1, last_page: 1 },
    };
}

function multiPageResponse(page = 1, lastPage = 3) {
    return {
        data: [MATCH_STUB],
        meta: { current_page: page, per_page: 25, total: lastPage * 25, last_page: lastPage },
    };
}

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    vi.clearAllMocks();
});

describe('/match SlashCommandBuilder', () => {
    it('exports data with name=match', () => {
        expect(data.name).toBe('match');
    });

    it('declares 4 subcommands (list, info, signup, leave)', () => {
        const json = data.toJSON();
        const subs = (json.options ?? []).map((o: { name: string }) => o.name);
        expect(subs).toEqual(['list', 'info', 'signup', 'leave']);
    });
});

describe('/match list subcommand', () => {
    it('defers reply ephemeral as the first awaited statement', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 25, total: 0, last_page: 1 } });
        const interaction = makeInteraction('list');
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.deferReply).toHaveBeenCalledWith({
            flags: MessageFlags.Ephemeral,
        });
    });

    it('calls api.get with path /matches?page=1 and actsAsDiscordId', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 25, total: 0, last_page: 1 } });
        const interaction = makeInteraction('list');
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(api.get).toHaveBeenCalledWith('/matches?page=1', {
            actsAsDiscordId: INVOKER_DISCORD_ID,
        });
    });

    it('editReplies "No open matches." when the API returns an empty list', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 25, total: 0, last_page: 1 } });
        const interaction = makeInteraction('list');
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith('No open matches.');
    });

    it('does NOT include components when data is empty', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 25, total: 0, last_page: 1 } });
        const interaction = makeInteraction('list');
        await execute(interaction as unknown as ChatInputCommandInteraction);
        const arg = interaction.editReply.mock.calls[0]?.[0];
        // Empty state uses plain string — no components object
        expect(typeof arg).toBe('string');
    });

    it('does NOT include pagination components when meta.last_page === 1', async () => {
        vi.mocked(api.get).mockResolvedValue(singlePageResponse());
        const interaction = makeInteraction('list');
        await execute(interaction as unknown as ChatInputCommandInteraction);
        const arg = interaction.editReply.mock.calls[0]?.[0] as Record<string, unknown>;
        // When last_page === 1, components must be absent or empty
        const components = arg?.components as unknown[] | undefined;
        expect(!components || components.length === 0).toBe(true);
    });

    it('includes pagination components (Prev/Next ActionRow) when meta.last_page > 1', async () => {
        vi.mocked(api.get).mockResolvedValue(multiPageResponse(1, 3));
        const interaction = makeInteraction('list');
        await execute(interaction as unknown as ChatInputCommandInteraction);
        const arg = interaction.editReply.mock.calls[0]?.[0] as Record<string, unknown>;
        const components = arg?.components as unknown[] | undefined;
        expect(components).toBeDefined();
        expect(components!.length).toBeGreaterThan(0);
    });

    it('includes "Page 1 of N" text in the reply when meta.last_page > 1', async () => {
        vi.mocked(api.get).mockResolvedValue(multiPageResponse(1, 3));
        const interaction = makeInteraction('list');
        await execute(interaction as unknown as ChatInputCommandInteraction);
        const arg = interaction.editReply.mock.calls[0]?.[0];
        const argStr = JSON.stringify(arg);
        expect(argStr).toContain('Page 1 of 3');
    });

    it('renders match embeds for every item in data (no client-side slice)', async () => {
        const twoMatches = [MATCH_STUB, { ...MATCH_STUB, id: 'aaaaaaaa-0000-0000-0000-000000000000' }];
        vi.mocked(api.get).mockResolvedValue({
            data: twoMatches,
            meta: { current_page: 1, per_page: 25, total: 2, last_page: 1 },
        });
        const interaction = makeInteraction('list');
        await execute(interaction as unknown as ChatInputCommandInteraction);
        const arg = interaction.editReply.mock.calls[0]?.[0] as { embeds: unknown[] };
        // matchCard produces 1 embed per match
        expect(arg.embeds.length).toBe(2);
    });
});

describe('/match info subcommand', () => {
    // BotApiMatchController::show() wraps the DTO in a { data } envelope, so the
    // mock MUST mirror that shape. A bare DTO here would let a regression to
    // `api.get<PublicMatchData>` (no .data unwrap) pass silently — exactly the
    // bug that posted a "Match undefined" card in production.
    it('calls api.get(/matches/{id}) with actsAsDiscordId', async () => {
        vi.mocked(api.get).mockResolvedValue({
            data: {
                id: MATCH_UUID,
                status: 'open',
                scheduled_at: '2026-01-01T00:00:00Z',
                host_clan_id: null,
            },
        });
        const interaction = makeInteraction('info', { id: MATCH_UUID });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(api.get).toHaveBeenCalledWith(`/matches/${MATCH_UUID}`, {
            actsAsDiscordId: INVOKER_DISCORD_ID,
        });
    });

    it('defers reply before calling the API', async () => {
        vi.mocked(api.get).mockResolvedValue({
            data: {
                id: MATCH_UUID,
                status: 'open',
                scheduled_at: null,
                host_clan_id: null,
            },
        });
        const interaction = makeInteraction('info', { id: MATCH_UUID });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.deferReply).toHaveBeenCalledTimes(1);
        expect(interaction.deferReply).toHaveBeenCalledWith({
            flags: MessageFlags.Ephemeral,
        });
    });

    it('renders the card from the unwrapped { data } envelope (regression: bare read → "Match undefined")', async () => {
        vi.mocked(api.get).mockResolvedValue({
            data: {
                id: MATCH_UUID,
                status: 'open',
                scheduled_at: null,
                host_clan_id: null,
                title: null,
                description: null,
                game_match_type_id: null,
            },
        });
        const interaction = makeInteraction('info', { id: MATCH_UUID });
        await execute(interaction as unknown as ChatInputCommandInteraction);

        const arg = interaction.editReply.mock.calls[0]?.[0] as {
            embeds: { data: { title?: string; footer?: { text: string } } }[];
        };
        const embed = arg.embeds[0].data;
        // Footer is `Match id: <uuid>` and title falls back to `Match <uuid>`.
        // If the envelope is read bare, m.id is undefined → both say "undefined".
        expect(embed.footer?.text).toBe(`Match id: ${MATCH_UUID}`);
        expect(embed.title).toBe(`Match ${MATCH_UUID}`);
        expect(JSON.stringify(embed)).not.toContain('undefined');
    });
});

describe('/match signup subcommand', () => {
    it('does NOT call deferReply (Pitfall 1 corollary — modal must be initial response)', async () => {
        const interaction = makeInteraction('signup', { id: MATCH_UUID });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.deferReply).not.toHaveBeenCalled();
    });

    it('invokes interaction.showModal once', async () => {
        const interaction = makeInteraction('signup', { id: MATCH_UUID });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.showModal).toHaveBeenCalledTimes(1);
    });

    it('modal customId follows the m:o:<matchId> encoding (plan 05-08 customIds)', async () => {
        const interaction = makeInteraction('signup', { id: MATCH_UUID });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        const modalArg = interaction.showModal.mock.calls[0]?.[0];
        // ModalBuilder stores its data in `.data` (discord.js v14 builder convention).
        expect(modalArg.data.custom_id).toBe(`m:o:${MATCH_UUID}`);
    });
});

describe('/match leave subcommand', () => {
    it('calls api.delete(/matches/{id}/signups/{role}) with actsAsDiscordId', async () => {
        vi.mocked(api.delete).mockResolvedValue(undefined);
        const interaction = makeInteraction('leave', { id: MATCH_UUID, role: ROLE_UUID });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(api.delete).toHaveBeenCalledWith(
            `/matches/${MATCH_UUID}/signups/${ROLE_UUID}`,
            { actsAsDiscordId: INVOKER_DISCORD_ID },
        );
    });

    it('editReplies success message on api.delete success', async () => {
        vi.mocked(api.delete).mockResolvedValue(undefined);
        const interaction = makeInteraction('leave', { id: MATCH_UUID, role: ROLE_UUID });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith('You have left the match.');
    });

    it('editReplies scrubbed failure message on api.delete error', async () => {
        vi.mocked(api.delete).mockRejectedValue(new Error('Bot API DELETE /matches/x -> 422: capacity_full'));
        const interaction = makeInteraction('leave', { id: MATCH_UUID, role: ROLE_UUID });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith(
            expect.stringContaining('Failed to leave:'),
        );
    });
});
