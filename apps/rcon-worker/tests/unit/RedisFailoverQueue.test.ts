// Plan 08-11 task 1 — RedisFailoverQueue unit tests.
// Source: .planning/phases/08-rcon-automation/08-11-PLAN.md task 1 behaviour list.
//
// 4 cases:
//   1. Empty queue → no postEvents calls.
//   2. Queue has 3 items → postEvents called with parsed array; LTRIM trims on success.
//   3. postEvents returns 500 → items NOT trimmed; logger.warn called.
//   4. Two queues for two matches → both drained in one pass.
//
// Uses ioredis-mock to avoid a real Redis. ioredis-mock's `scanStream` is
// real enough for our SCAN MATCH 'rcon:queue:*' pattern.

import RedisMock from 'ioredis-mock';
import pino from 'pino';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { RedisFailoverQueue, queueKey } from '../../src/queue/RedisFailoverQueue.js';
import type { WebIngestClient, PostEventsResult } from '../../src/ingest/WebIngestClient.js';
import type { NormalisedEvent } from '../../src/crcon/CrconEventNormaliser.js';

const logger = pino({ level: 'silent' });

function makeEvent(streamId: string): NormalisedEvent {
    return {
        event_type: 'player_kill',
        crcon_action: 'KILL',
        crcon_stream_id: streamId,
        occurred_at: new Date(1715670000000).toISOString(),
        payload: {
            killer: { steam_id_64: '76561198000000001', name: 'P1' },
            victim: { steam_id_64: '76561198000000002', name: 'P2' },
            weapon: 'MG42',
        },
    };
}

function makeWebClient(status: number): {
    client: WebIngestClient;
    postEvents: ReturnType<typeof vi.fn>;
} {
    const postEvents = vi.fn<(...args: never[]) => Promise<PostEventsResult>>(async () => ({
        status,
        body: { ok: status >= 200 && status < 300 },
    }));
    const client = { postEvents } as unknown as WebIngestClient;
    return { client, postEvents };
}

describe('RedisFailoverQueue', () => {
    let redis: InstanceType<typeof RedisMock>;

    beforeEach(() => {
        redis = new RedisMock();
    });

    afterEach(async () => {
        await redis.flushall();
        await redis.disconnect();
        vi.restoreAllMocks();
    });

    it('empty queue → no postEvents calls', async () => {
        const { client, postEvents } = makeWebClient(200);
        const drainer = new RedisFailoverQueue({ redis, webClient: client, logger });
        await drainer.drain();
        expect(postEvents).not.toHaveBeenCalled();
    });

    it('queue has 3 items → postEvents called with parsed array; LTRIM trims on success', async () => {
        const matchId = '11111111-2222-3333-4444-555555555555';
        const key = queueKey(matchId);
        const batch1 = [makeEvent('L-1'), makeEvent('L-2')];
        const batch2 = [makeEvent('L-3')];
        const batch3 = [makeEvent('L-4'), makeEvent('L-5')];
        await redis.rpush(key, JSON.stringify(batch1));
        await redis.rpush(key, JSON.stringify(batch2));
        await redis.rpush(key, JSON.stringify(batch3));

        const { client, postEvents } = makeWebClient(202);
        const drainer = new RedisFailoverQueue({ redis, webClient: client, logger });

        await drainer.drain();

        expect(postEvents).toHaveBeenCalledTimes(1);
        const [calledMatchId, calledEvents] = postEvents.mock.calls[0]!;
        expect(calledMatchId).toBe(matchId);
        // 2 + 1 + 2 = 5 events flattened.
        expect(calledEvents).toHaveLength(5);
        expect((calledEvents as NormalisedEvent[]).map((e) => e.crcon_stream_id)).toEqual([
            'L-1',
            'L-2',
            'L-3',
            'L-4',
            'L-5',
        ]);

        const remaining = await redis.llen(key);
        expect(remaining).toBe(0);
    });

    it('postEvents returns 500 → items NOT trimmed; logger.warn called', async () => {
        const matchId = '11111111-2222-3333-4444-555555555555';
        const key = queueKey(matchId);
        await redis.rpush(key, JSON.stringify([makeEvent('L-1')]));
        await redis.rpush(key, JSON.stringify([makeEvent('L-2')]));

        const warnSpy = vi.spyOn(logger, 'warn');
        const { client, postEvents } = makeWebClient(500);
        const drainer = new RedisFailoverQueue({ redis, webClient: client, logger });

        await drainer.drain();

        expect(postEvents).toHaveBeenCalledTimes(1);
        // 500 → items retained.
        const remaining = await redis.llen(key);
        expect(remaining).toBe(2);
        expect(warnSpy).toHaveBeenCalled();
        const warned = warnSpy.mock.calls.map((c) => JSON.stringify(c)).join('\n');
        expect(warned).toContain('non-2xx');
        warnSpy.mockRestore();
    });

    it('two queues for two matches → both drained in one pass', async () => {
        const matchA = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        const matchB = '11111111-2222-3333-4444-555555555555';
        await redis.rpush(queueKey(matchA), JSON.stringify([makeEvent('A-1')]));
        await redis.rpush(queueKey(matchB), JSON.stringify([makeEvent('B-1')]));

        const { client, postEvents } = makeWebClient(204);
        const drainer = new RedisFailoverQueue({ redis, webClient: client, logger });

        await drainer.drain();

        expect(postEvents).toHaveBeenCalledTimes(2);
        const calledMatchIds = postEvents.mock.calls.map((c) => c[0]).sort();
        expect(calledMatchIds).toEqual([matchA, matchB].sort());

        expect(await redis.llen(queueKey(matchA))).toBe(0);
        expect(await redis.llen(queueKey(matchB))).toBe(0);
    });
});
