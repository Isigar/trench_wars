// Trenchwars bot — /profile slash command tests (Wave 7 GREEN flip).
//
// Source: .planning/phases/05-discord-bot-v1/05-09-PLAN.md task 3.
// Replaces the Wave 0 RED stub. Asserts SC-1:
//   - data exports a single required `user` option (Discord user mention)
//   - execute defers reply ephemeral as the FIRST awaited statement
//     (Pitfall 1 — 3s window mitigation)
//   - v1 implementation emits a redirect-to-web message (Open Question Q5
//     resolution — PlayerPrivacyGate respected by deferring to the
//     website's gate)
//
// Plan 05-12 will swap the redirect for a viewer-aware
// /api/bot/users/by-discord/{id} call; this test will need to be updated
// at that time.

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

import { data, execute } from '../../src/commands/profile.js';
import { api } from '../../src/services/api.js';

const INVOKER_DISCORD_ID = '100000000000000001';
const TARGET_DISCORD_ID = '200000000000000002';

type MockInteraction = {
    user: { id: string };
    options: {
        getUser: ReturnType<typeof vi.fn>;
    };
    deferReply: ReturnType<typeof vi.fn>;
    editReply: ReturnType<typeof vi.fn>;
    reply: ReturnType<typeof vi.fn>;
    isRepliable: () => boolean;
    replied: boolean;
    deferred: boolean;
};

function makeInteraction(targetDiscordId: string): MockInteraction {
    return {
        user: { id: INVOKER_DISCORD_ID },
        options: {
            getUser: vi.fn().mockReturnValue({ id: targetDiscordId }),
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

describe('/profile SlashCommandBuilder', () => {
    it('exports data with name=profile', () => {
        expect(data.name).toBe('profile');
    });

    it('declares a required user option', () => {
        const json = data.toJSON();
        const opts = json.options ?? [];
        expect(opts).toHaveLength(1);
        expect(opts[0]?.name).toBe('user');
        expect(opts[0]?.required).toBe(true);
    });
});

describe('/profile execute', () => {
    it('defers reply ephemeral as the first awaited statement', async () => {
        const interaction = makeInteraction(TARGET_DISCORD_ID);
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.deferReply).toHaveBeenCalledWith({
            flags: MessageFlags.Ephemeral,
        });
    });

    it('does NOT call api.get — v1 redirect-to-web stub (Open Question Q5)', async () => {
        const interaction = makeInteraction(TARGET_DISCORD_ID);
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(api.get).not.toHaveBeenCalled();
    });

    it('editReplies a message referencing the target user + "website"', async () => {
        const interaction = makeInteraction(TARGET_DISCORD_ID);
        await execute(interaction as unknown as ChatInputCommandInteraction);
        const reply = interaction.editReply.mock.calls[0]?.[0] as string;
        expect(reply).toContain(TARGET_DISCORD_ID);
        expect(reply.toLowerCase()).toContain('website');
    });
});
