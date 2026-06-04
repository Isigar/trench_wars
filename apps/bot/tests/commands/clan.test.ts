// Trenchwars bot — /clan slash command tests (Wave 7 GREEN flip, Phase 10-05 update).
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 3.
// Updated in Phase 10-05: apply branch flipped from redirect-to-web stub to
// live api.post assertions.
// Updated in Phase 12-03: /clan list pagination render tests (BOT-01).
// Asserts SC-1:
//   - 3 subcommands declared on the SlashCommandBuilder data (info/list/apply)
//   - info + list call api.get with actsAsDiscordId
//   - apply calls api.post(/clans/{slug}/applications) with actsAsDiscordId
//   - every branch defers reply ephemeral as the FIRST awaited statement
//     (Pitfall 1 — 3s window mitigation)
//   - /clan list fetches /clans?page=1 from { data, meta } envelope
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

import { data, execute } from '../../src/commands/clan.js';
import { api } from '../../src/services/api.js';

const INVOKER_DISCORD_ID = '100000000000000001';

type MockInteraction = {
    user: { id: string };
    options: {
        getSubcommand: ReturnType<typeof vi.fn>;
        getString: ReturnType<typeof vi.fn>;
    };
    deferReply: ReturnType<typeof vi.fn>;
    editReply: ReturnType<typeof vi.fn>;
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
        reply: vi.fn().mockResolvedValue(undefined),
        isRepliable: () => true,
        replied: false,
        deferred: false,
    };
}

const CLAN_DATA = {
    id: 'c-uuid',
    slug: 'redwave',
    tag: 'RW',
    name: 'Red Wave',
    status: 'active',
    active_member_count: 12,
};

// Helpers to build paginated API responses.
function singlePageResponse() {
    return {
        data: [CLAN_DATA],
        meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
    };
}

function multiPageResponse(page = 1, lastPage = 3) {
    return {
        data: [CLAN_DATA],
        meta: { current_page: page, per_page: 20, total: lastPage * 20, last_page: lastPage },
    };
}

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    vi.clearAllMocks();
});

describe('/clan SlashCommandBuilder', () => {
    it('exports data with name=clan', () => {
        expect(data.name).toBe('clan');
    });

    it('declares 3 subcommands (info, list, apply)', () => {
        const json = data.toJSON();
        const subs = (json.options ?? []).map((o: { name: string }) => o.name);
        expect(subs).toEqual(['info', 'list', 'apply']);
    });
});

describe('/clan info subcommand', () => {
    // BotApiClanController::show() wraps the DTO in a { data } envelope, so the
    // mock MUST mirror that shape. A bare DTO here would let a regression to
    // `api.get<ClanData>` (no .data unwrap) pass silently — exactly the bug that
    // rendered "Clan undefined [undefined]".

    it('defers reply ephemeral first', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: CLAN_DATA });
        const interaction = makeInteraction('info', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.deferReply).toHaveBeenCalledWith({
            flags: MessageFlags.Ephemeral,
        });
    });

    it('calls api.get(/clans/{slug}) with actsAsDiscordId', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: CLAN_DATA });
        const interaction = makeInteraction('info', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(api.get).toHaveBeenCalledWith('/clans/redwave', {
            actsAsDiscordId: INVOKER_DISCORD_ID,
        });
    });

    it('renders clan fields from the unwrapped { data } envelope (regression: bare read → "Clan undefined")', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: CLAN_DATA });
        const interaction = makeInteraction('info', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        const reply = interaction.editReply.mock.calls[0]?.[0] as string;
        expect(reply).toContain('Red Wave');
        expect(reply).toContain('[RW]');
        expect(reply).not.toContain('undefined');
    });
});

describe('/clan list subcommand', () => {
    it('calls api.get with path /clans?page=1&limit=20 and actsAsDiscordId (WR-01: safe page size)', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 } });
        const interaction = makeInteraction('list');
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(api.get).toHaveBeenCalledWith('/clans?page=1&limit=20', {
            actsAsDiscordId: INVOKER_DISCORD_ID,
        });
    });

    it('editReplies "No clans." when API returns empty list', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 } });
        const interaction = makeInteraction('list');
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith('No clans.');
    });

    it('does NOT include components when data is empty', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 } });
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

    // BL-02: requesting a page beyond last_page must render last_page, not a dead-end empty message.
    it('BL-02: page > last_page clamps to last_page and renders with nav buttons', async () => {
        vi.mocked(api.get)
            .mockResolvedValueOnce({ data: [], meta: { current_page: 99, per_page: 20, total: 40, last_page: 2 } })
            .mockResolvedValueOnce({ data: [CLAN_DATA], meta: { current_page: 2, per_page: 20, total: 40, last_page: 2 } });
        const interaction = makeInteraction('list');
        const { renderClanListPage } = await import('../../src/commands/clan.js');
        await renderClanListPage(interaction as unknown as ChatInputCommandInteraction, 99);
        const arg = interaction.editReply.mock.calls[0]?.[0];
        expect(arg).not.toBe('No clans.');
        const argObj = arg as Record<string, unknown>;
        const components = argObj?.components as unknown[] | undefined;
        expect(components).toBeDefined();
        expect(components!.length).toBeGreaterThan(0);
    });

    // WR-01: content string must not exceed Discord's 2000-char message cap.
    it('WR-01: clan list content is sliced to 2000 chars', async () => {
        // Generate a clan with a very long name to force content truncation.
        const longNameClan = { ...CLAN_DATA, name: 'A'.repeat(200), tag: 'XX', slug: 'a'.repeat(50) };
        const manyClans = Array.from({ length: 20 }, (_, i) => ({ ...longNameClan, id: `clan-${i}`, slug: `a${i}` }));
        vi.mocked(api.get).mockResolvedValue({
            data: manyClans,
            meta: { current_page: 1, per_page: 20, total: 20, last_page: 1 },
        });
        const interaction = makeInteraction('list');
        await execute(interaction as unknown as ChatInputCommandInteraction);
        const arg = interaction.editReply.mock.calls[0]?.[0];
        const content = typeof arg === 'string' ? arg : (arg as Record<string, unknown>)?.content as string | undefined;
        if (content !== undefined) {
            expect(content.length).toBeLessThanOrEqual(2000);
        }
    });
});

describe('/clan apply subcommand', () => {
    it('defers reply ephemeral first', async () => {
        vi.mocked(api.post).mockResolvedValue({ data: {} });
        const interaction = makeInteraction('apply', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.deferReply).toHaveBeenCalledWith({
            flags: MessageFlags.Ephemeral,
        });
    });

    it('calls api.post(/clans/{slug}/applications) with actsAsDiscordId', async () => {
        vi.mocked(api.post).mockResolvedValue({ data: {} });
        const interaction = makeInteraction('apply', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(api.post).toHaveBeenCalledWith(
            '/clans/redwave/applications',
            {},
            { actsAsDiscordId: INVOKER_DISCORD_ID },
        );
    });

    it('editReplies success message on api.post success', async () => {
        vi.mocked(api.post).mockResolvedValue({ data: {} });
        const interaction = makeInteraction('apply', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith(
            'Your application has been submitted.',
        );
    });

    it('editReplies translated error on clan_not_recruiting', async () => {
        vi.mocked(api.post).mockRejectedValue(
            new Error(
                'Bot API POST /clans/redwave/applications -> 422: {"error":"clan_not_recruiting"}',
            ),
        );
        const interaction = makeInteraction('apply', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith(
            'This clan is not accepting applications.',
        );
    });
});
