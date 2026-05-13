// Trenchwars bot — RSVP button handler tests (Wave 9 GREEN flip).
//
// Source: .planning/phases/05-discord-bot-v1/05-10-PLAN.md task 3.
// Replaces the Wave 0 RED stub. Asserts:
//
//   - decodeButtonId routing: match_open_signup_modal -> showModal (no defer
//     expected; the dispatcher does not pre-defer modal-opening buttons)
//   - match_signup -> api.post(/matches/{id}/signups) with actsAsDiscordId
//   - match_leave -> api.delete(/matches/{id}/signups/{role}) with
//     actsAsDiscordId
//   - translateError maps the 4 typed errors to friendly user copy
//   - Unknown / malformed customId emits 'Unknown button.'
//
// vi.mock for ../../src/services/api replaces the module-level `api` object
// with spies. Hoisted before the import-under-test resolves.

import { type ButtonInteraction } from 'discord.js';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('../../src/services/api.js', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
        request: vi.fn(),
    },
}));

import { handle, translateError } from '../../src/components/rsvpButton.js';
import { encodeButtonId } from '../../src/lib/customIds.js';
import { api } from '../../src/services/api.js';

const INVOKER_DISCORD_ID = '100000000000000001';
const MATCH_UUID = '01234567-89ab-cdef-0123-456789abcdef';
const ROLE_UUID = 'fedcba98-7654-3210-fedc-ba9876543210';

type MockButtonInteraction = {
    customId: string;
    user: { id: string };
    deferReply: ReturnType<typeof vi.fn>;
    editReply: ReturnType<typeof vi.fn>;
    showModal: ReturnType<typeof vi.fn>;
    reply: ReturnType<typeof vi.fn>;
    isRepliable: () => boolean;
    replied: boolean;
    deferred: boolean;
};

function makeButtonInteraction(
    customId: string,
    overrides: Partial<MockButtonInteraction> = {},
): MockButtonInteraction {
    return {
        customId,
        user: { id: INVOKER_DISCORD_ID },
        deferReply: vi.fn().mockResolvedValue(undefined),
        editReply: vi.fn().mockResolvedValue(undefined),
        showModal: vi.fn().mockResolvedValue(undefined),
        reply: vi.fn().mockResolvedValue(undefined),
        isRepliable: () => true,
        replied: false,
        deferred: true, // dispatcher has already deferred for non-modal-opening buttons
        ...overrides,
    };
}

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    vi.clearAllMocks();
});

describe('rsvpButton.handle — match_open_signup_modal', () => {
    it('invokes interaction.showModal (does NOT editReply)', async () => {
        const customId = encodeButtonId({
            kind: 'match_open_signup_modal',
            matchId: MATCH_UUID,
        });
        const interaction = makeButtonInteraction(customId, { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        expect(interaction.showModal).toHaveBeenCalledTimes(1);
        expect(interaction.editReply).not.toHaveBeenCalled();
    });

    it('modal customId echoes the m:o:<matchId> scheme', async () => {
        const customId = encodeButtonId({
            kind: 'match_open_signup_modal',
            matchId: MATCH_UUID,
        });
        const interaction = makeButtonInteraction(customId, { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        const modalArg = interaction.showModal.mock.calls[0]?.[0];
        expect(modalArg.data.custom_id).toBe(`m:o:${MATCH_UUID}`);
    });
});

describe('rsvpButton.handle — match_signup', () => {
    it('calls api.post(/matches/{id}/signups, {game_role_id}) with actsAsDiscordId', async () => {
        vi.mocked(api.post).mockResolvedValue(undefined);
        const customId = encodeButtonId({
            kind: 'match_signup',
            matchId: MATCH_UUID,
            gameRoleId: ROLE_UUID,
        });
        const interaction = makeButtonInteraction(customId);
        await handle(interaction as unknown as ButtonInteraction);
        expect(api.post).toHaveBeenCalledWith(
            `/matches/${MATCH_UUID}/signups`,
            { game_role_id: ROLE_UUID },
            { actsAsDiscordId: INVOKER_DISCORD_ID },
        );
    });

    it('editReplies success message on api.post success', async () => {
        vi.mocked(api.post).mockResolvedValue(undefined);
        const customId = encodeButtonId({
            kind: 'match_signup',
            matchId: MATCH_UUID,
            gameRoleId: ROLE_UUID,
        });
        const interaction = makeButtonInteraction(customId);
        await handle(interaction as unknown as ButtonInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith(
            'Signed up to the match.',
        );
    });

    it('editReplies translated message on api.post match_not_open error', async () => {
        vi.mocked(api.post).mockRejectedValue(
            new Error(
                'Bot API POST /matches/xxx/signups -> 422: {"error":"match_not_open","message":"..."}',
            ),
        );
        const customId = encodeButtonId({
            kind: 'match_signup',
            matchId: MATCH_UUID,
            gameRoleId: ROLE_UUID,
        });
        const interaction = makeButtonInteraction(customId);
        await handle(interaction as unknown as ButtonInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith(
            'This match is not open for signups.',
        );
    });
});

describe('rsvpButton.handle — match_leave', () => {
    it('calls api.delete(/matches/{id}/signups/{role}) with actsAsDiscordId', async () => {
        vi.mocked(api.delete).mockResolvedValue(undefined);
        const customId = encodeButtonId({
            kind: 'match_leave',
            matchId: MATCH_UUID,
            gameRoleId: ROLE_UUID,
        });
        const interaction = makeButtonInteraction(customId);
        await handle(interaction as unknown as ButtonInteraction);
        expect(api.delete).toHaveBeenCalledWith(
            `/matches/${MATCH_UUID}/signups/${ROLE_UUID}`,
            { actsAsDiscordId: INVOKER_DISCORD_ID },
        );
    });

    it('editReplies success message on api.delete success', async () => {
        vi.mocked(api.delete).mockResolvedValue(undefined);
        const customId = encodeButtonId({
            kind: 'match_leave',
            matchId: MATCH_UUID,
            gameRoleId: ROLE_UUID,
        });
        const interaction = makeButtonInteraction(customId);
        await handle(interaction as unknown as ButtonInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith(
            'You have left the match.',
        );
    });
});

describe('rsvpButton.handle — clan_apply (v1 redirect-to-web stub)', () => {
    it('does NOT call api.post (v1 stub per D-05-09-B)', async () => {
        const customId = encodeButtonId({
            kind: 'clan_apply',
            clanId: 'c-uuid',
        });
        const interaction = makeButtonInteraction(customId);
        await handle(interaction as unknown as ButtonInteraction);
        expect(api.post).not.toHaveBeenCalled();
    });

    it('editReplies a message mentioning the website', async () => {
        const customId = encodeButtonId({
            kind: 'clan_apply',
            clanId: 'c-uuid',
        });
        const interaction = makeButtonInteraction(customId);
        await handle(interaction as unknown as ButtonInteraction);
        const reply = interaction.editReply.mock.calls[0]?.[0] as string;
        expect(reply.toLowerCase()).toContain('website');
    });
});

describe('rsvpButton.handle — unknown customId', () => {
    it('editReplies "Unknown button." when deferred', async () => {
        const interaction = makeButtonInteraction('garbage:not:a:button', {
            deferred: true,
        });
        await handle(interaction as unknown as ButtonInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith('Unknown button.');
    });

    it('replies ephemeral "Unknown button." when undeferred', async () => {
        const interaction = makeButtonInteraction('garbage:not:a:button', {
            deferred: false,
        });
        await handle(interaction as unknown as ButtonInteraction);
        expect(interaction.reply).toHaveBeenCalledWith(
            expect.objectContaining({ content: 'Unknown button.' }),
        );
    });
});

describe('rsvpButton.translateError', () => {
    it('maps match_not_open', () => {
        const e = new Error('something match_not_open something');
        expect(translateError(e)).toBe('This match is not open for signups.');
    });

    it('maps capacity_full', () => {
        const e = new Error('422: {"error":"capacity_full"}');
        expect(translateError(e)).toBe('This role is full.');
    });

    it('maps tag_restricted', () => {
        const e = new Error('Bot API: tag_restricted');
        expect(translateError(e)).toBe(
            'Your clan tags are not permitted on this match.',
        );
    });

    it('maps already_signed_up', () => {
        const e = new Error('already_signed_up');
        expect(translateError(e)).toBe(
            'You are already signed up to this match.',
        );
    });

    it('falls through to "Failed: <message>" for unknown errors', () => {
        const e = new Error('some unrelated 500');
        expect(translateError(e)).toMatch(/^Failed: /);
    });
});
