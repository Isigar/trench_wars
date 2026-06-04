// Trenchwars bot — RSVP button handler tests (Wave 9 GREEN flip, Phase 10-05 update).
//
// Source: .planning/phases/05-discord-bot-v1/05-10-PLAN.md task 3.
// Updated in Phase 10-05: clan_apply describe block flipped from redirect-to-web
// stub assertions to live api.post assertions; translateError extended with
// 3 new clan error code cases.
// Updated in Phase 12-04: list_page branch — pg: buttons call interaction.update()
// (NOT reply/editReply) with a re-fetched page payload; malformed pg: ids are
// already covered by the existing "Unknown button" path.
// Asserts:
//
//   - decodeButtonId routing: match_open_signup_modal -> showModal (no defer
//     expected; the dispatcher does not pre-defer modal-opening buttons)
//   - match_signup -> api.post(/matches/{id}/signups) with actsAsDiscordId
//   - match_leave -> api.delete(/matches/{id}/signups/{role}) with
//     actsAsDiscordId
//   - clan_apply -> api.post(/clans/{clanId}/applications) with actsAsDiscordId
//   - list_page (pg:m:N) -> api.get(/matches?page=N) + interaction.update()
//   - list_page (pg:c:N) -> api.get(/clans?page=N) + interaction.update()
//   - list_page update() payload reflects new page bounds (Prev/Next disabled)
//   - translateError maps the 7 typed errors to friendly user copy
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
    update: ReturnType<typeof vi.fn>;
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
        update: vi.fn().mockResolvedValue(undefined),
        isRepliable: () => true,
        replied: false,
        deferred: true, // dispatcher has already deferred for non-modal-opening buttons
        ...overrides,
    };
}

// Minimal match stub for list_page responses.
const MATCH_STUB = {
    id: MATCH_UUID,
    status: 'open',
    scheduled_at: null,
    host_clan_id: null,
    title: null,
    description: null,
    game_match_type_id: null,
};

// Minimal clan stub for list_page responses.
const CLAN_STUB = {
    id: 'clan-uuid',
    name: 'Test Clan',
    tag: 'TC',
    slug: 'test-clan',
    status: 'active',
    active_member_count: 5,
};

function matchListPageResponse(page = 1, lastPage = 3) {
    return {
        data: [MATCH_STUB],
        meta: { current_page: page, per_page: 5, total: lastPage * 5, last_page: lastPage },
    };
}

function clanListPageResponse(page = 1, lastPage = 2) {
    return {
        data: [CLAN_STUB],
        meta: { current_page: page, per_page: 20, total: lastPage * 20, last_page: lastPage },
    };
}

function emptyMatchPageResponse(requestedPage: number, lastPage: number) {
    return {
        data: [] as typeof MATCH_STUB[],
        meta: { current_page: requestedPage, per_page: 5, total: lastPage * 5, last_page: lastPage },
    };
}

function emptyClanPageResponse(requestedPage: number, lastPage: number) {
    return {
        data: [] as typeof CLAN_STUB[],
        meta: { current_page: requestedPage, per_page: 20, total: lastPage * 20, last_page: lastPage },
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

describe('rsvpButton.handle — clan_apply', () => {
    it('calls api.post(/clans/{clanId}/applications) with actsAsDiscordId', async () => {
        vi.mocked(api.post).mockResolvedValue({ data: {} });
        const customId = encodeButtonId({
            kind: 'clan_apply',
            clanId: 'c-uuid',
        });
        const interaction = makeButtonInteraction(customId);
        await handle(interaction as unknown as ButtonInteraction);
        expect(api.post).toHaveBeenCalledWith(
            '/clans/c-uuid/applications',
            {},
            { actsAsDiscordId: INVOKER_DISCORD_ID },
        );
    });

    it('editReplies success message on api.post success', async () => {
        vi.mocked(api.post).mockResolvedValue({ data: {} });
        const customId = encodeButtonId({
            kind: 'clan_apply',
            clanId: 'c-uuid',
        });
        const interaction = makeButtonInteraction(customId);
        await handle(interaction as unknown as ButtonInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith(
            'Your application has been submitted.',
        );
    });

    it('editReplies translated error on clan_not_recruiting', async () => {
        vi.mocked(api.post).mockRejectedValue(
            new Error(
                'Bot API POST /clans/c-uuid/applications -> 422: {"error":"clan_not_recruiting"}',
            ),
        );
        const customId = encodeButtonId({
            kind: 'clan_apply',
            clanId: 'c-uuid',
        });
        const interaction = makeButtonInteraction(customId);
        await handle(interaction as unknown as ButtonInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith(
            'This clan is not accepting applications.',
        );
    });
});

describe('rsvpButton.handle — list_page (match)', () => {
    it('calls api.get(/matches?page=2&limit=5) with actsAsDiscordId when customId is pg:m:2 (BL-01)', async () => {
        vi.mocked(api.get).mockResolvedValue(matchListPageResponse(2, 3));
        const interaction = makeButtonInteraction('pg:m:2', { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        expect(api.get).toHaveBeenCalledWith('/matches?page=2&limit=5', {
            actsAsDiscordId: INVOKER_DISCORD_ID,
        });
    });

    it('calls interaction.update() (NOT reply/editReply) with the rebuilt page-2 payload', async () => {
        vi.mocked(api.get).mockResolvedValue(matchListPageResponse(2, 3));
        const interaction = makeButtonInteraction('pg:m:2', { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        expect(interaction.update).toHaveBeenCalledTimes(1);
        expect(interaction.reply).not.toHaveBeenCalled();
        expect(interaction.editReply).not.toHaveBeenCalled();
    });

    it('update() payload includes embeds and pagination components for multi-page match list', async () => {
        vi.mocked(api.get).mockResolvedValue(matchListPageResponse(2, 3));
        const interaction = makeButtonInteraction('pg:m:2', { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        const updateArg = interaction.update.mock.calls[0]?.[0] as Record<string, unknown>;
        const components = updateArg?.components as unknown[] | undefined;
        expect(components).toBeDefined();
        expect(components!.length).toBeGreaterThan(0);
        expect(updateArg?.embeds).toBeDefined();
    });

    it('update() payload content contains "Page 2 of 3" for match page 2 of 3', async () => {
        vi.mocked(api.get).mockResolvedValue(matchListPageResponse(2, 3));
        const interaction = makeButtonInteraction('pg:m:2', { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        const updateArg = interaction.update.mock.calls[0]?.[0];
        expect(JSON.stringify(updateArg)).toContain('Page 2 of 3');
    });

    it('Prev button is disabled when on page 1 (first page bound)', async () => {
        vi.mocked(api.get).mockResolvedValue(matchListPageResponse(1, 2));
        const interaction = makeButtonInteraction('pg:m:1', { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        const updateArg = interaction.update.mock.calls[0]?.[0] as Record<string, unknown>;
        const components = updateArg?.components as Array<{ toJSON: () => { components: Array<{ disabled?: boolean }> } }> | undefined;
        // The ActionRow's first button (Prev) must be disabled on page 1
        const row = components?.[0]?.toJSON();
        expect(row?.components[0]?.disabled).toBe(true);
    });

    it('Next button is disabled when on the last page (last page bound)', async () => {
        vi.mocked(api.get).mockResolvedValue(matchListPageResponse(2, 2));
        const interaction = makeButtonInteraction('pg:m:2', { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        const updateArg = interaction.update.mock.calls[0]?.[0] as Record<string, unknown>;
        const components = updateArg?.components as Array<{ toJSON: () => { components: Array<{ disabled?: boolean }> } }> | undefined;
        // The ActionRow's second button (Next) must be disabled on last page
        const row = components?.[0]?.toJSON();
        expect(row?.components[1]?.disabled).toBe(true);
    });

    // BL-02: stale pg:m:99 button when last_page=2 must render page 2 with nav, not dead-end empty.
    it('BL-02: pg:m:99 with last_page=2 clamps to page 2 and renders with nav buttons', async () => {
        vi.mocked(api.get)
            .mockResolvedValueOnce(emptyMatchPageResponse(99, 2))
            .mockResolvedValueOnce(matchListPageResponse(2, 2));
        const interaction = makeButtonInteraction('pg:m:99', { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        const updateArg = interaction.update.mock.calls[0]?.[0] as Record<string, unknown>;
        // Must not show the dead-end plain-text empty message.
        expect(updateArg?.content).not.toBe('No open matches.');
        // Must include nav components.
        const components = updateArg?.components as unknown[] | undefined;
        expect(components).toBeDefined();
        expect(components!.length).toBeGreaterThan(0);
    });
});

describe('rsvpButton.handle — list_page (clan)', () => {
    it('calls api.get(/clans?page=3&limit=20) with actsAsDiscordId when customId is pg:c:3 (WR-01)', async () => {
        vi.mocked(api.get).mockResolvedValue(clanListPageResponse(3, 4));
        const interaction = makeButtonInteraction('pg:c:3', { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        expect(api.get).toHaveBeenCalledWith('/clans?page=3&limit=20', {
            actsAsDiscordId: INVOKER_DISCORD_ID,
        });
    });

    it('calls interaction.update() (NOT reply/editReply) with clan page 3 payload', async () => {
        vi.mocked(api.get).mockResolvedValue(clanListPageResponse(3, 4));
        const interaction = makeButtonInteraction('pg:c:3', { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        expect(interaction.update).toHaveBeenCalledTimes(1);
        expect(interaction.reply).not.toHaveBeenCalled();
        expect(interaction.editReply).not.toHaveBeenCalled();
    });

    it('update() payload content contains "Page 3 of 4" for clan page 3 of 4', async () => {
        vi.mocked(api.get).mockResolvedValue(clanListPageResponse(3, 4));
        const interaction = makeButtonInteraction('pg:c:3', { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        const updateArg = interaction.update.mock.calls[0]?.[0];
        expect(JSON.stringify(updateArg)).toContain('Page 3 of 4');
    });

    it('update() payload includes pagination components when last_page > 1', async () => {
        vi.mocked(api.get).mockResolvedValue(clanListPageResponse(1, 2));
        const interaction = makeButtonInteraction('pg:c:1', { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        const updateArg = interaction.update.mock.calls[0]?.[0] as Record<string, unknown>;
        const components = updateArg?.components as unknown[] | undefined;
        expect(components).toBeDefined();
        expect(components!.length).toBeGreaterThan(0);
    });

    it('update() on error does not leave interaction unacknowledged', async () => {
        vi.mocked(api.get).mockRejectedValue(new Error('network failure'));
        const interaction = makeButtonInteraction('pg:c:1', { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        // Must still call update() to acknowledge the interaction (avoid "application did not respond")
        expect(interaction.update).toHaveBeenCalledTimes(1);
    });

    // BL-02: stale pg:c:99 button when last_page=2 must render page 2 with nav, not dead-end empty.
    it('BL-02: pg:c:99 with last_page=2 clamps to page 2 and renders with nav buttons', async () => {
        vi.mocked(api.get)
            .mockResolvedValueOnce(emptyClanPageResponse(99, 2))
            .mockResolvedValueOnce(clanListPageResponse(2, 2));
        const interaction = makeButtonInteraction('pg:c:99', { deferred: false });
        await handle(interaction as unknown as ButtonInteraction);
        const updateArg = interaction.update.mock.calls[0]?.[0] as Record<string, unknown>;
        expect(updateArg?.content).not.toBe('No clans.');
        const components = updateArg?.components as unknown[] | undefined;
        expect(components).toBeDefined();
        expect(components!.length).toBeGreaterThan(0);
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

    it('maps clan_not_recruiting', () => {
        const e = new Error('422: {"error":"clan_not_recruiting"}');
        expect(translateError(e)).toBe('This clan is not accepting applications.');
    });

    it('maps already_in_clan', () => {
        const e = new Error('422: {"error":"already_in_clan"}');
        expect(translateError(e)).toBe('You are already a member of a clan.');
    });

    it('maps duplicate_application', () => {
        const e = new Error('422: {"error":"duplicate_application"}');
        expect(translateError(e)).toBe(
            'You already have a pending application to this clan.',
        );
    });
});
