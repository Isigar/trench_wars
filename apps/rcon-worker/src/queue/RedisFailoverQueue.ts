// Plan 08-11 task 1 — Redis-backed retry queue for events that fail ingest
// (web returns non-2xx). Source: 08-11-PLAN.md <interfaces> RedisFailoverQueue.
//
// Key shape: `rcon:queue:{matchId}` — one list per match, RPUSH on failure
// (MatchLifecycleManager) / LRANGE+LTRIM on drain (this class).
//
// Threat refs:
//   - T-08-11-02 (single failing match queues unbounded events): each drain
//     pass reads ≤100 items per match, so the memory ceiling per pass is
//     bounded; if a queue grows forever it indicates a stuck web service →
//     plan 08-12 audit alerting catches it. ltrim() keeps the queue tight.
//
// Drain semantics:
//   - Each pass uses SCAN (not KEYS — non-blocking against large keyspaces).
//   - For each `rcon:queue:*` key, LRANGE 0 99 reads up to 100 batches.
//   - On HTTP 2xx, LTRIM removes the drained range so retries do not
//     re-deliver the same events.
//   - On HTTP non-2xx, items stay queued; next drain pass retries.

import type { Redis } from 'ioredis';
import type { Logger } from 'pino';
import type { WebIngestClient } from '../ingest/WebIngestClient.js';
import type { NormalisedEvent } from '../crcon/CrconEventNormaliser.js';

const QUEUE_PREFIX = 'rcon:queue:';
const SCAN_MATCH = `${QUEUE_PREFIX}*`;
const SCAN_COUNT = 50;
const BATCHES_PER_PASS = 100;

export interface RedisFailoverQueueOptions {
    redis: Redis;
    webClient: WebIngestClient;
    logger: Logger;
}

/** Public key helper — used by MatchLifecycleManager to LPUSH on ingest failure. */
export function queueKey(matchId: string): string {
    return `${QUEUE_PREFIX}${matchId}`;
}

/**
 * Periodic drainer for the Redis fallback queue.
 *
 * start(intervalMs?): kicks off setInterval (default 5_000ms).
 * stop(): clears the interval.
 * drain(): one full pass — public for tests + manual triggers.
 */
export class RedisFailoverQueue {
    private timer: NodeJS.Timeout | undefined;

    constructor(private readonly opts: RedisFailoverQueueOptions) {}

    start(intervalMs = 5_000): void {
        this.timer = setInterval(() => {
            void this.drain();
        }, intervalMs);
    }

    stop(): void {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = undefined;
        }
    }

    /**
     * Single drain pass. SCAN for queue keys, drain up to BATCHES_PER_PASS
     * stored batches per key, POST to /api/internal/match/{id}/events, LTRIM
     * on success.
     *
     * Each LRANGE entry is a JSON-stringified `NormalisedEvent[]` (because
     * MatchLifecycleManager.flush LPUSHes the per-flush batch as one JSON
     * string). We flatten across batches and POST the combined array; this
     * trades wire-call count for per-match throughput (one POST drains 100
     * stored batches).
     */
    async drain(): Promise<void> {
        const stream = this.opts.redis.scanStream({
            match: SCAN_MATCH,
            count: SCAN_COUNT,
        });

        for await (const chunk of stream) {
            for (const key of chunk as string[]) {
                await this.drainKey(key);
            }
        }
    }

    private async drainKey(key: string): Promise<void> {
        const matchId = key.startsWith(QUEUE_PREFIX) ? key.slice(QUEUE_PREFIX.length) : key;
        const items = await this.opts.redis.lrange(key, 0, BATCHES_PER_PASS - 1);
        if (items.length === 0) return;

        // Each stored item is a JSON.stringified NormalisedEvent[] batch.
        // Flatten across all read batches into one POST.
        const events: NormalisedEvent[] = [];
        for (const raw of items) {
            try {
                const parsed = JSON.parse(raw) as NormalisedEvent[];
                if (Array.isArray(parsed)) {
                    events.push(...parsed);
                }
            } catch (e) {
                this.opts.logger.warn(
                    { err: (e as Error).message, matchId },
                    'failover drain: malformed queue item dropped',
                );
                // Continue — we still LTRIM the malformed item on success of others;
                // a single corrupt entry should not block the queue forever.
            }
        }

        if (events.length === 0) {
            // All items were malformed — trim them so the queue doesn't loop.
            await this.opts.redis.ltrim(key, items.length, -1);
            return;
        }

        let result;
        try {
            result = await this.opts.webClient.postEvents(matchId, events);
        } catch (e) {
            this.opts.logger.warn(
                { err: (e as Error).message, matchId, batches: items.length },
                'failover drain: postEvents threw',
            );
            return;
        }

        if (result.status >= 200 && result.status < 300) {
            // LTRIM removes [0..items.length-1] inclusive by keeping [items.length, -1].
            await this.opts.redis.ltrim(key, items.length, -1);
        } else {
            this.opts.logger.warn(
                { matchId, status: result.status, batches: items.length },
                'failover drain: web ingest returned non-2xx; items retained for next pass',
            );
        }
    }
}
