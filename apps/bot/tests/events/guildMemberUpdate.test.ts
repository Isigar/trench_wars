// Trenchwars bot — guildMemberUpdate event handler tests (Wave 10 GREEN flip).
//
// Source: .planning/phases/05-discord-bot-v1/05-11-PLAN.md task 3.
// Replaces the Wave 0 RED stub. Asserts SC-4 (reconciliation second half):
//
//   - detects added role -> POSTs /discord-events/role-change with action=add
//   - detects removed role -> POSTs with action=remove
//   - handles multiple deltas in one update (one POST per delta)
//   - swallows api.post failures (the gateway listener wraps in try/catch)
//   - no-ops on identical role sets (no deltas)
//   - both add + remove emitted in a single update — one of each per role
//
// The handler diffs `roles.cache` (a discord.js Collection — a Map subclass
// with a `.filter` method that takes a predicate). For unit testability the
// handler's per-event logic is exported as `handleGuildMemberUpdate(old, new)`;
// these tests invoke that helper directly rather than going through the
// `client.on(...)` registration plumbing.

import { type GuildMember, type PartialGuildMember } from 'discord.js';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

vi.mock('../../src/env.js', () => ({
    env: {
        DISCORD_BOT_TOKEN: 'test-token',
        DISCORD_APPLICATION_ID: 'test-app',
        DISCORD_GUILD_ID: 'test-guild',
        WEB_API_URL: 'http://test',
        WEB_API_TOKEN: 'test-api-token',
        OUTBOUND_POLL_INTERVAL_MS: 5000,
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

import {
    handleGuildMemberUpdate,
    registerGuildMemberUpdateHandler,
} from '../../src/events/guildMemberUpdate.js';
import { api } from '../../src/services/api.js';

const USER_DISCORD_ID = '987654321098765432';
const ROLE_A = '111111111111111111';
const ROLE_B = '222222222222222222';
const ROLE_C = '333333333333333333';

/**
 * Build a Map-shaped roles.cache. The handler only consumes the `.filter`
 * and `.has` methods + iterates [key, role] tuples — a plain Map plus a
 * filter shim satisfies the contract. We attach a discord.js-Collection-
 * compatible filter so the SUT can call .filter(predicate).
 */
function makeRoleCache(roleIds: string[]): Map<string, { id: string }> {
    const cache = new Map<string, { id: string }>();
    for (const id of roleIds) {
        cache.set(id, { id });
    }
    Object.assign(cache, {
        filter(
            predicate: (value: { id: string }, key: string) => boolean,
        ): Map<string, { id: string }> {
            const result = makeRoleCache([]);
            for (const [k, v] of cache.entries()) {
                if (predicate(v, k)) {
                    result.set(k, v);
                }
            }
            return result;
        },
    });
    return cache;
}

function makeMember(roleIds: string[]): GuildMember {
    return {
        id: USER_DISCORD_ID,
        roles: { cache: makeRoleCache(roleIds) },
    } as unknown as GuildMember;
}

function makePartialMember(roleIds: string[]): PartialGuildMember {
    return {
        id: USER_DISCORD_ID,
        roles: { cache: makeRoleCache(roleIds) },
    } as unknown as PartialGuildMember;
}

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    vi.clearAllMocks();
});

describe('handleGuildMemberUpdate', () => {
    it('detects an added role -> POSTs /discord-events/role-change with action=add', async () => {
        vi.mocked(api.post).mockResolvedValue({});
        const oldMember = makeMember([ROLE_A]);
        const newMember = makeMember([ROLE_A, ROLE_B]);

        await handleGuildMemberUpdate(oldMember, newMember);

        expect(api.post).toHaveBeenCalledTimes(1);
        expect(api.post).toHaveBeenCalledWith('/discord-events/role-change', {
            user_discord_id: USER_DISCORD_ID,
            role_discord_id: ROLE_B,
            action: 'add',
        });
    });

    it('detects a removed role -> POSTs /discord-events/role-change with action=remove', async () => {
        vi.mocked(api.post).mockResolvedValue({});
        const oldMember = makeMember([ROLE_A, ROLE_B]);
        const newMember = makeMember([ROLE_A]);

        await handleGuildMemberUpdate(oldMember, newMember);

        expect(api.post).toHaveBeenCalledTimes(1);
        expect(api.post).toHaveBeenCalledWith('/discord-events/role-change', {
            user_discord_id: USER_DISCORD_ID,
            role_discord_id: ROLE_B,
            action: 'remove',
        });
    });

    it('handles multiple deltas in one update (one POST per delta)', async () => {
        vi.mocked(api.post).mockResolvedValue({});
        // Remove ROLE_A; add ROLE_B + ROLE_C -> 3 POSTs total.
        const oldMember = makeMember([ROLE_A]);
        const newMember = makeMember([ROLE_B, ROLE_C]);

        await handleGuildMemberUpdate(oldMember, newMember);

        expect(api.post).toHaveBeenCalledTimes(3);
        const calls = vi.mocked(api.post).mock.calls;
        const payloads = calls.map(([, body]) => body);
        expect(payloads).toContainEqual({
            user_discord_id: USER_DISCORD_ID,
            role_discord_id: ROLE_A,
            action: 'remove',
        });
        expect(payloads).toContainEqual({
            user_discord_id: USER_DISCORD_ID,
            role_discord_id: ROLE_B,
            action: 'add',
        });
        expect(payloads).toContainEqual({
            user_discord_id: USER_DISCORD_ID,
            role_discord_id: ROLE_C,
            action: 'add',
        });
    });

    it('no-ops on identical role sets (no deltas)', async () => {
        vi.mocked(api.post).mockResolvedValue({});
        const oldMember = makeMember([ROLE_A, ROLE_B]);
        const newMember = makeMember([ROLE_A, ROLE_B]);

        await handleGuildMemberUpdate(oldMember, newMember);

        expect(api.post).not.toHaveBeenCalled();
    });

    it('accepts a PartialGuildMember as oldMember (partials enabled in client.ts)', async () => {
        vi.mocked(api.post).mockResolvedValue({});
        const oldMember = makePartialMember([ROLE_A]);
        const newMember = makeMember([ROLE_A, ROLE_B]);

        await handleGuildMemberUpdate(oldMember, newMember);

        expect(api.post).toHaveBeenCalledTimes(1);
        expect(api.post).toHaveBeenCalledWith('/discord-events/role-change', {
            user_discord_id: USER_DISCORD_ID,
            role_discord_id: ROLE_B,
            action: 'add',
        });
    });

    it('emits both add and remove deltas in a single update', async () => {
        vi.mocked(api.post).mockResolvedValue({});
        const oldMember = makeMember([ROLE_A]);
        const newMember = makeMember([ROLE_B]);

        await handleGuildMemberUpdate(oldMember, newMember);

        expect(api.post).toHaveBeenCalledTimes(2);
        const payloads = vi
            .mocked(api.post)
            .mock.calls.map(([, body]) => body);
        expect(payloads).toContainEqual({
            user_discord_id: USER_DISCORD_ID,
            role_discord_id: ROLE_A,
            action: 'remove',
        });
        expect(payloads).toContainEqual({
            user_discord_id: USER_DISCORD_ID,
            role_discord_id: ROLE_B,
            action: 'add',
        });
    });
});

describe('registerGuildMemberUpdateHandler', () => {
    it('catches and logs errors thrown by the inner handler (does not propagate)', async () => {
        // We invoke the registered listener directly via a captured callback.
        const errSpy = vi
            .spyOn(console, 'error')
            .mockImplementation(() => {});
        vi.mocked(api.post).mockRejectedValue(new Error('api down'));

        let capturedListener:
            | ((
                  o: GuildMember | PartialGuildMember,
                  n: GuildMember,
              ) => Promise<void>)
            | null = null;
        const stubClient = {
            on: vi.fn((event: string, listener: typeof capturedListener) => {
                if (event === 'guildMemberUpdate') {
                    capturedListener = listener;
                }
            }),
        };

        registerGuildMemberUpdateHandler(
            stubClient as unknown as Parameters<
                typeof registerGuildMemberUpdateHandler
            >[0],
        );

        expect(capturedListener).not.toBeNull();

        const oldMember = makeMember([ROLE_A]);
        const newMember = makeMember([ROLE_A, ROLE_B]);
        await expect(
            (capturedListener as unknown as (
                o: GuildMember | PartialGuildMember,
                n: GuildMember,
            ) => Promise<void>)(oldMember, newMember),
        ).resolves.toBeUndefined();
        expect(errSpy).toHaveBeenCalled();
        expect(errSpy.mock.calls[0]?.[0]).toBe('[bot/guildMemberUpdate]');

        errSpy.mockRestore();
    });

    it('registers a listener on Events.GuildMemberUpdate', () => {
        const onSpy = vi.fn();
        const stubClient = { on: onSpy };
        registerGuildMemberUpdateHandler(
            stubClient as unknown as Parameters<
                typeof registerGuildMemberUpdateHandler
            >[0],
        );
        expect(onSpy).toHaveBeenCalledWith(
            'guildMemberUpdate',
            expect.any(Function),
        );
    });
});
