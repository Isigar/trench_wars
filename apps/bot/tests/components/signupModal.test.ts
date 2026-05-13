// Trenchwars bot — signup modal submit handler tests (Wave 9 GREEN flip).
//
// Source: .planning/phases/05-discord-bot-v1/05-10-PLAN.md task 3.
// Replaces the Wave 0 RED stub. Asserts SC-2:
//
//   - reads role text input + calls api.post(/matches/{id}/signups,
//     {game_role_id}) with actsAsDiscordId = interaction.user.id
//   - rejects malformed role UUID with friendly message; api.post NOT called
//   - translates each of the 4 typed errors
//   - buildSignupModal customId matches the encodeButtonId('m:o:<matchId>')
//     scheme

import { type ModalSubmitInteraction } from 'discord.js';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('../../src/services/api.js', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
        request: vi.fn(),
    },
}));

import { buildSignupModal, handle } from '../../src/components/signupModal.js';
import { api } from '../../src/services/api.js';

const INVOKER_DISCORD_ID = '100000000000000001';
const MATCH_UUID = '01234567-89ab-cdef-0123-456789abcdef';
const ROLE_UUID = 'fedcba98-7654-3210-fedc-ba9876543210';

type MockModalSubmitInteraction = {
    customId: string;
    user: { id: string };
    fields: { getTextInputValue: ReturnType<typeof vi.fn> };
    editReply: ReturnType<typeof vi.fn>;
};

function makeModalInteraction(
    customId: string,
    roleInput: string,
): MockModalSubmitInteraction {
    return {
        customId,
        user: { id: INVOKER_DISCORD_ID },
        fields: {
            getTextInputValue: vi.fn().mockReturnValue(roleInput),
        },
        editReply: vi.fn().mockResolvedValue(undefined),
    };
}

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    vi.clearAllMocks();
});

describe('buildSignupModal', () => {
    it('produces a ModalBuilder with customId m:o:<matchId>', () => {
        const modal = buildSignupModal(MATCH_UUID);
        expect(modal.data.custom_id).toBe(`m:o:${MATCH_UUID}`);
    });

    it('declares a TextInput with customId "role"', () => {
        const modal = buildSignupModal(MATCH_UUID);
        const json = modal.toJSON();
        // ActionRow > TextInput nested shape.
        const firstRow = json.components[0];
        expect(firstRow).toBeDefined();
        const textInput = firstRow!.components[0] as {
            custom_id: string;
            required: boolean;
        };
        expect(textInput.custom_id).toBe('role');
        expect(textInput.required).toBe(true);
    });
});

describe('signupModal.handle — happy path', () => {
    it('reads role input + calls api.post with body + actsAsDiscordId', async () => {
        vi.mocked(api.post).mockResolvedValue(undefined);
        const interaction = makeModalInteraction(`m:o:${MATCH_UUID}`, ROLE_UUID);
        await handle(interaction as unknown as ModalSubmitInteraction);

        expect(interaction.fields.getTextInputValue).toHaveBeenCalledWith('role');
        expect(api.post).toHaveBeenCalledWith(
            `/matches/${MATCH_UUID}/signups`,
            { game_role_id: ROLE_UUID },
            { actsAsDiscordId: INVOKER_DISCORD_ID },
        );
        expect(interaction.editReply).toHaveBeenCalledWith(
            'Signed up to the match.',
        );
    });

    it('trims whitespace around the role input before validating', async () => {
        vi.mocked(api.post).mockResolvedValue(undefined);
        const interaction = makeModalInteraction(
            `m:o:${MATCH_UUID}`,
            `  ${ROLE_UUID}  `,
        );
        await handle(interaction as unknown as ModalSubmitInteraction);
        expect(api.post).toHaveBeenCalledWith(
            `/matches/${MATCH_UUID}/signups`,
            { game_role_id: ROLE_UUID },
            { actsAsDiscordId: INVOKER_DISCORD_ID },
        );
    });
});

describe('signupModal.handle — UUID validation', () => {
    it('rejects malformed role UUID with friendly message; api.post NOT called', async () => {
        const interaction = makeModalInteraction(
            `m:o:${MATCH_UUID}`,
            'not-a-uuid',
        );
        await handle(interaction as unknown as ModalSubmitInteraction);
        expect(api.post).not.toHaveBeenCalled();
        expect(interaction.editReply).toHaveBeenCalledWith('Invalid role UUID.');
    });

    it('rejects empty role input', async () => {
        const interaction = makeModalInteraction(`m:o:${MATCH_UUID}`, '   ');
        await handle(interaction as unknown as ModalSubmitInteraction);
        expect(api.post).not.toHaveBeenCalled();
        expect(interaction.editReply).toHaveBeenCalledWith('Invalid role UUID.');
    });
});

describe('signupModal.handle — error translation', () => {
    function rejectWith(msg: string) {
        vi.mocked(api.post).mockRejectedValue(new Error(msg));
    }

    it('maps match_not_open', async () => {
        rejectWith(
            '422: {"error":"match_not_open","message":"This match is not open for signups."}',
        );
        const interaction = makeModalInteraction(`m:o:${MATCH_UUID}`, ROLE_UUID);
        await handle(interaction as unknown as ModalSubmitInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith(
            'This match is not open for signups.',
        );
    });

    it('maps capacity_full', async () => {
        rejectWith('422: {"error":"capacity_full"}');
        const interaction = makeModalInteraction(`m:o:${MATCH_UUID}`, ROLE_UUID);
        await handle(interaction as unknown as ModalSubmitInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith('This role is full.');
    });

    it('maps tag_restricted', async () => {
        rejectWith('422: {"error":"tag_restricted"}');
        const interaction = makeModalInteraction(`m:o:${MATCH_UUID}`, ROLE_UUID);
        await handle(interaction as unknown as ModalSubmitInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith(
            'Your clan tags are not permitted on this match.',
        );
    });

    it('maps already_signed_up', async () => {
        rejectWith('422: {"error":"already_signed_up"}');
        const interaction = makeModalInteraction(`m:o:${MATCH_UUID}`, ROLE_UUID);
        await handle(interaction as unknown as ModalSubmitInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith(
            'You are already signed up to this match.',
        );
    });
});

describe('signupModal.handle — bad customId', () => {
    it('emits "Unknown modal." when customId is not m:o:<matchId>', async () => {
        const interaction = makeModalInteraction(
            'something-else:m:l:zzz',
            ROLE_UUID,
        );
        await handle(interaction as unknown as ModalSubmitInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith('Unknown modal.');
        expect(api.post).not.toHaveBeenCalled();
    });
});
