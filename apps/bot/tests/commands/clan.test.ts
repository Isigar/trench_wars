// Trenchwars bot — /clan slash command tests (Wave 7 GREEN flip, Phase 10-05 update).
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 3.
// Updated in Phase 10-05: apply branch flipped from redirect-to-web stub to
// live api.post assertions. Asserts SC-1:
//   - 3 subcommands declared on the SlashCommandBuilder data (info/list/apply)
//   - info + list call api.get with actsAsDiscordId
//   - apply calls api.post(/clans/{slug}/applications) with actsAsDiscordId
//   - every branch defers reply ephemeral as the FIRST awaited statement
//     (Pitfall 1 — 3s window mitigation)

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
    const CLAN_DATA = {
        id: 'c-uuid',
        slug: 'redwave',
        tag: 'RW',
        name: 'Red Wave',
        status: 'active',
        active_member_count: 12,
    };

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
    it('calls api.get(/clans) with actsAsDiscordId', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: [] });
        const interaction = makeInteraction('list');
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(api.get).toHaveBeenCalledWith('/clans', {
            actsAsDiscordId: INVOKER_DISCORD_ID,
        });
    });

    it('editReplies "No clans." when API returns empty list', async () => {
        vi.mocked(api.get).mockResolvedValue({ data: [] });
        const interaction = makeInteraction('list');
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith('No clans.');
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
