// Trenchwars bot — outbound poll-worker tests (Wave 10 GREEN flip).
//
// Source: .planning/phases/05-discord-bot-v1/05-11-PLAN.md task 3.
// Replaces the Wave 0 RED stub. Asserts SC-3:
//
//   processOutboundTick
//     - GETs /outbound-messages?status=pending&limit=20 on every invocation
//     - calls render(client, row) for each returned row
//     - on render success: POST /outbound-messages/{id}/sent with sent_message_id
//     - on render throw: POST /outbound-messages/{id}/failed with last_error (clamped to 2000 chars)
//     - poll-level errors do NOT exit (caller's setInterval keeps firing)
//     - markFailed failures are logged but do not propagate
//
//   startOutboundWorker
//     - returns a NodeJS.Timeout
//     - overlap-skip guard: if a previous tick is still running when the next
//       interval fires, the new tick is dropped (asserted by mocking api.get
//       to return a Promise that never resolves and counting the resulting
//       api.get call count after advancing through 3 intervals)
//     - respects intervalMs constructor argument
//
// vi.mock for ../../src/services/api + ../../src/services/render replaces the
// module-level singletons with spies. Hoisted before the import-under-test
// resolves.

import type { Client } from 'discord.js';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';

// Mock env BEFORE the SUT import so OUTBOUND_POLL_INTERVAL_MS is available
// without requiring real Discord secrets at test time. The api / render
// mocks neutralise the network side-effects too.
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

vi.mock('../../src/services/render.js', () => ({
    render: vi.fn(),
}));

import {
    __resetRunningFlagForTests,
    processOutboundTick,
    startOutboundWorker,
} from '../../src/services/outbound.js';
import { api } from '../../src/services/api.js';
import { render } from '../../src/services/render.js';
import type { OutboundRow } from '../../src/types/apiContracts.js';

const STUB_CLIENT = {} as Client;

function makeRow(overrides: Partial<OutboundRow> = {}): OutboundRow {
    return {
        id: 'row-1',
        channel_id: 'chan-1',
        message_type: 'match_announce',
        status: 'pending',
        payload: { kind: 'match_announce_new', match_id: 'match-1' },
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

beforeEach(() => {
    vi.clearAllMocks();
    __resetRunningFlagForTests();
});

afterEach(() => {
    vi.useRealTimers();
    __resetRunningFlagForTests();
});

describe('processOutboundTick', () => {
    it('polls /outbound-messages?status=pending&limit=20 on each tick', async () => {
        vi.mocked(api.get).mockResolvedValue([]);
        await processOutboundTick(STUB_CLIENT);
        expect(api.get).toHaveBeenCalledTimes(1);
        expect(api.get).toHaveBeenCalledWith(
            '/outbound-messages?status=pending&limit=20',
        );
    });

    it('calls render for each row and marks sent with the discordMessageId on success', async () => {
        const rows = [makeRow({ id: 'row-a' }), makeRow({ id: 'row-b' })];
        vi.mocked(api.get).mockResolvedValue(rows);
        vi.mocked(render).mockResolvedValueOnce({
            discordMessageId: 'discord-msg-a',
        });
        vi.mocked(render).mockResolvedValueOnce({
            discordMessageId: 'discord-msg-b',
        });
        vi.mocked(api.post).mockResolvedValue({});

        await processOutboundTick(STUB_CLIENT);

        expect(render).toHaveBeenCalledTimes(2);
        expect(render).toHaveBeenNthCalledWith(1, STUB_CLIENT, rows[0]);
        expect(render).toHaveBeenNthCalledWith(2, STUB_CLIENT, rows[1]);
        expect(api.post).toHaveBeenCalledWith(
            '/outbound-messages/row-a/sent',
            { sent_message_id: 'discord-msg-a' },
        );
        expect(api.post).toHaveBeenCalledWith(
            '/outbound-messages/row-b/sent',
            { sent_message_id: 'discord-msg-b' },
        );
    });

    it('marks failed with err.message when render throws', async () => {
        vi.mocked(api.get).mockResolvedValue([makeRow({ id: 'row-fail' })]);
        vi.mocked(render).mockRejectedValue(new Error('boom'));
        vi.mocked(api.post).mockResolvedValue({});

        await processOutboundTick(STUB_CLIENT);

        expect(api.post).toHaveBeenCalledWith(
            '/outbound-messages/row-fail/failed',
            { last_error: 'boom' },
        );
    });

    it('clamps last_error to 2000 chars', async () => {
        vi.mocked(api.get).mockResolvedValue([makeRow({ id: 'row-big' })]);
        const longMessage = 'x'.repeat(2500);
        vi.mocked(render).mockRejectedValue(new Error(longMessage));
        vi.mocked(api.post).mockResolvedValue({});

        await processOutboundTick(STUB_CLIENT);

        const failCall = vi
            .mocked(api.post)
            .mock.calls.find(([path]) => path.endsWith('/failed'));
        expect(failCall).toBeDefined();
        const body = failCall?.[1] as { last_error: string };
        expect(body.last_error).toHaveLength(2000);
    });

    it('coerces non-Error throwables to String(err) for last_error', async () => {
        vi.mocked(api.get).mockResolvedValue([makeRow({ id: 'row-str' })]);
        vi.mocked(render).mockRejectedValue('not an error object');
        vi.mocked(api.post).mockResolvedValue({});

        await processOutboundTick(STUB_CLIENT);

        expect(api.post).toHaveBeenCalledWith(
            '/outbound-messages/row-str/failed',
            { last_error: 'not an error object' },
        );
    });

    it('does NOT throw on markFailed failure (nested catch swallows)', async () => {
        vi.mocked(api.get).mockResolvedValue([makeRow({ id: 'row-ack-fail' })]);
        vi.mocked(render).mockRejectedValue(new Error('render fail'));
        vi.mocked(api.post).mockRejectedValue(new Error('api down'));
        const errSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

        await expect(processOutboundTick(STUB_CLIENT)).resolves.toBeUndefined();
        expect(errSpy).toHaveBeenCalled();

        errSpy.mockRestore();
    });

    it('continues processing remaining rows when one row fails', async () => {
        const rows = [
            makeRow({ id: 'row-fail' }),
            makeRow({ id: 'row-ok' }),
        ];
        vi.mocked(api.get).mockResolvedValue(rows);
        vi.mocked(render).mockRejectedValueOnce(new Error('fail-1'));
        vi.mocked(render).mockResolvedValueOnce({
            discordMessageId: 'discord-ok',
        });
        vi.mocked(api.post).mockResolvedValue({});

        await processOutboundTick(STUB_CLIENT);

        expect(render).toHaveBeenCalledTimes(2);
        // Failed first, then sent second
        const calls = vi.mocked(api.post).mock.calls.map(([path]) => path);
        expect(calls).toContain('/outbound-messages/row-fail/failed');
        expect(calls).toContain('/outbound-messages/row-ok/sent');
    });
});

describe('startOutboundWorker', () => {
    it('returns a NodeJS.Timeout handle that can be cleared', () => {
        vi.useFakeTimers();
        vi.mocked(api.get).mockResolvedValue([]);
        const handle = startOutboundWorker(STUB_CLIENT, 5000);
        expect(handle).toBeDefined();
        clearInterval(handle);
    });

    it('invokes the tick on every intervalMs elapsed', async () => {
        vi.useFakeTimers();
        vi.mocked(api.get).mockResolvedValue([]);

        const handle = startOutboundWorker(STUB_CLIENT, 1000);
        // No tick yet — setInterval fires AFTER the first interval, not at t=0.
        expect(api.get).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1000);
        expect(api.get).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(1000);
        expect(api.get).toHaveBeenCalledTimes(2);

        clearInterval(handle);
    });

    it('overlap-skip guard: a second tick is dropped while the first is still in flight', async () => {
        vi.useFakeTimers();

        // api.get returns a Promise that we will resolve manually so we can
        // hold a tick in-flight while triggering the next interval.
        let resolveFirstGet: (rows: OutboundRow[]) => void = () => {};
        const firstGet = new Promise<OutboundRow[]>((res) => {
            resolveFirstGet = res;
        });
        vi.mocked(api.get).mockReturnValueOnce(firstGet);

        const handle = startOutboundWorker(STUB_CLIENT, 1000);

        // Tick 1 fires — sets running=true, awaits api.get which is pending.
        await vi.advanceTimersByTimeAsync(1000);
        expect(api.get).toHaveBeenCalledTimes(1);

        // Tick 2 fires — running is still true; the new tick must be dropped.
        await vi.advanceTimersByTimeAsync(1000);
        expect(api.get).toHaveBeenCalledTimes(1);

        // Tick 3 fires — still running; still dropped.
        await vi.advanceTimersByTimeAsync(1000);
        expect(api.get).toHaveBeenCalledTimes(1);

        // Resolve the first tick — running flips to false.
        resolveFirstGet([]);
        await Promise.resolve();
        await Promise.resolve();

        // Now arm a second api.get response and advance one more interval.
        vi.mocked(api.get).mockResolvedValueOnce([]);
        await vi.advanceTimersByTimeAsync(1000);
        expect(api.get).toHaveBeenCalledTimes(2);

        clearInterval(handle);
    });

    it('respects intervalMs override (constructor argument)', async () => {
        vi.useFakeTimers();
        vi.mocked(api.get).mockResolvedValue([]);

        const handle = startOutboundWorker(STUB_CLIENT, 250);

        // After 100ms — no tick yet (interval is 250).
        await vi.advanceTimersByTimeAsync(100);
        expect(api.get).not.toHaveBeenCalled();

        // After another 200ms — total 300ms elapsed > 250 -> first tick fires.
        await vi.advanceTimersByTimeAsync(200);
        expect(api.get).toHaveBeenCalledTimes(1);

        clearInterval(handle);
    });
});
