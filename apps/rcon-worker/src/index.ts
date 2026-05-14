// Plan 08-11 task 2 — rcon-worker entrypoint. Replaces the Wave-0 placeholder
// (plan 08-01) with the real boot: load config, build ioredis client, build
// WebIngestClient, start BookingScheduler + RedisFailoverQueue drainer, install
// SIGTERM/SIGINT graceful shutdown.
//
// Source: 08-11-PLAN.md <interfaces> index.ts.
//
// Healthcheck: docker-compose `healthcheck` is `pgrep node` — once main() is
// running the node process satisfies the probe. The scheduler+drainer keep the
// process alive via their setInterval timers; no event-loop pump needed.
//
// Shutdown: SIGTERM/SIGINT closes intervals, calls .stop() on each manager,
// disconnects redis, and exits 0. Container orchestrators (Railway, docker
// compose stop) send SIGTERM first; we honour both signals.

import { Redis } from 'ioredis';
import { BookingScheduler } from './booking/BookingScheduler.js';
import { loadConfig } from './config.js';
import { WebIngestClient } from './ingest/WebIngestClient.js';
import { logger } from './logging/logger.js';
import { RedisFailoverQueue } from './queue/RedisFailoverQueue.js';

async function main(): Promise<void> {
    const cfg = loadConfig();
    const redisUrl = cfg.REDIS_URL ?? 'redis://redis:6379';
    const redis = new Redis(redisUrl, {
        // Keep ioredis from spamming the event loop on a transient redis outage —
        // we want to surface failures via logger.warn at the drainer, not via
        // an unhandled-rejection loop here.
        maxRetriesPerRequest: 3,
        enableOfflineQueue: true,
    });

    redis.on('error', (err: Error) => {
        logger.warn({ err: err.message }, 'redis client error');
    });

    const webClient = new WebIngestClient({
        secret: cfg.WEB_HMAC_SECRET,
        baseUrl: cfg.WEB_INTERNAL_URL,
        logger,
    });

    const scheduler = new BookingScheduler({
        webClient,
        logger,
        redis,
        secret: cfg.WEB_HMAC_SECRET,
        pollIntervalMs: cfg.POLL_INTERVAL_MS,
    });

    const drainer = new RedisFailoverQueue({ redis, webClient, logger });

    scheduler.start();
    drainer.start();
    logger.info(
        {
            pollIntervalMs: cfg.POLL_INTERVAL_MS,
            webUrl: cfg.WEB_INTERNAL_URL,
            redisUrl,
        },
        'rcon-worker started',
    );

    let shuttingDown = false;
    const shutdown = (signal: NodeJS.Signals): void => {
        if (shuttingDown) return;
        shuttingDown = true;
        logger.info({ signal }, 'rcon-worker received signal; shutting down');
        scheduler.stop();
        drainer.stop();
        redis.disconnect();
        // Give pino a tick to flush, then exit.
        setTimeout(() => process.exit(0), 100);
    };
    process.on('SIGTERM', shutdown);
    process.on('SIGINT', shutdown);
}

main().catch((e: Error) => {
    logger.fatal({ err: e.message, stack: e.stack }, 'fatal startup error');
    process.exit(1);
});
