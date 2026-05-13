// Trenchwars bot — /clan slash command tests (Wave 7 GREEN flip).
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 3.
// Replaces the Wave 0 RED stub. Asserts SC-1:
//   - 3 subcommands declared on the SlashCommandBuilder data (info/list/apply)
//   - info + list call api.get with actsAsDiscordId
//   - apply ships v1 redirect-to-web message (Open Question Q2 resolution)
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
    it('defers reply ephemeral first', async () => {
        vi.mocked(api.get).mockResolvedValue({
            id: 'c-uuid',
            slug: 'redwave',
            tag: 'RW',
            name: 'Red Wave',
            status: 'active',
            active_member_count: 12,
        });
        const interaction = makeInteraction('info', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.deferReply).toHaveBeenCalledWith({
            flags: MessageFlags.Ephemeral,
        });
    });

    it('calls api.get(/clans/{slug}) with actsAsDiscordId', async () => {
        vi.mocked(api.get).mockResolvedValue({
            id: 'c-uuid',
            slug: 'redwave',
            tag: 'RW',
            name: 'Red Wave',
            status: 'active',
            active_member_count: 12,
        });
        const interaction = makeInteraction('info', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(api.get).toHaveBeenCalledWith('/clans/redwave', {
            actsAsDiscordId: INVOKER_DISCORD_ID,
        });
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
    it('does NOT call api.post (v1 redirect-to-web stub — Open Question Q2)', async () => {
        const interaction = makeInteraction('apply', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(api.post).not.toHaveBeenCalled();
    });

    it('editReplies a message referencing the slug + "website"', async () => {
        const interaction = makeInteraction('apply', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        const reply = interaction.editReply.mock.calls[0]?.[0] as string;
        expect(reply).toContain('redwave');
        expect(reply.toLowerCase()).toContain('website');
    });

    it('defers reply ephemeral first', async () => {
        const interaction = makeInteraction('apply', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.deferReply).toHaveBeenCalledWith({
            flags: MessageFlags.Ephemeral,
        });
    });
});
