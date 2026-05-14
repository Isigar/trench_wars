// Plan 08-11 task 2 — per-booking CRCON session + event batcher + manual_error
// emitter on hard failure. Source: 08-11-PLAN.md <interfaces> MatchLifecycleManager.
//
// Lifecycle:
//   1. start(): GET /api/internal/match-servers/{id}/credentials → open CrconClient.
//      If credentials fetch fails → emit synthetic manual_error → complete=true.
//   2. onLogs: normalise each entry → push into buffer → flush at batchSize≥10.
//   3. flush(): POST /api/internal/match/{id}/events. On non-2xx, LPUSH onto
//      `rcon:queue:{matchId}` for the RedisFailoverQueue drainer to retry.
//   4. tryComplete(): scheduled for reserved_to + 60s grace; sets complete=true
//      whether or not match_end was seen (the web side has its own CloseMatchJob
//      with manual-entry flag handling — see plan 08-08).
//
// Threat refs:
//   - T-08-11-04 (manual_error payload contains stack): emitManualError sends
//     only {kind, detail} — no stack trace. The worker logs the stack locally
//     via pino but does not transmit (T-08-10-05 redact list still applies).
//
// Cross-tier invariant:
//   On CRCON unreachable / auth_failed at session open, the worker MUST emit
//   a synthetic manual_error event so the apps/web RconUnreachableFlagsManualTest
//   invariant holds end-to-end. This is the cross-tier handshake the test asserts.

import type { Redis } from 'ioredis';
import type { Logger } from 'pino';
import { CrconClient } from '../crcon/CrconClient.js';
import { normalise } from '../crcon/CrconEventNormaliser.js';
import type { NormalisedEvent } from '../crcon/CrconEventNormaliser.js';
import type { WebIngestClient } from '../ingest/WebIngestClient.js';
import type { BookingDueData } from './types.js';
import { queueKey } from '../queue/RedisFailoverQueue.js';

/** Wire shape returned by /api/internal/match-servers/{id}/credentials. */
interface CredentialsResponse {
    host: string;
    port_rcon: number;
    api_token: string;
}

export interface MatchLifecycleManagerOptions {
    booking: BookingDueData;
    webClient: WebIngestClient;
    logger: Logger;
    redis: Redis;
    secret: string;
    /**
     * Override the 2s flush cadence. Production leaves this unset (default
     * 2_000ms); integration tests use a short cadence (e.g. 50ms) for
     * deterministic CI.
     */
    flushIntervalMs?: number;
    /**
     * Override the per-batch flush threshold. Default 10 per the plan; tests
     * can drop this for faster assertions.
     */
    batchSize?: number;
    /**
     * Override the +60s grace window after reserved_to. Production leaves
     * this unset (default 60_000ms); tests use a short value to exercise
     * tryComplete without real-time waits.
     */
    completeGraceMs?: number;
}

const DEFAULT_FLUSH_INTERVAL_MS = 2_000;
const DEFAULT_BATCH_SIZE = 10;
const DEFAULT_COMPLETE_GRACE_MS = 60_000;

/**
 * Per-booking session manager — owns one CrconClient + one event buffer.
 *
 * The class is intentionally chunky: it bridges three concerns (CRCON ws →
 * normaliser → web ingest with Redis fallback) into one stateful object so
 * BookingScheduler can manage them as a single unit (one Map entry per
 * booking id).
 */
export class MatchLifecycleManager {
    private crcon: CrconClient | undefined;
    private buffer: NormalisedEvent[] = [];
    private flushTimer: NodeJS.Timeout | undefined;
    private completeTimer: NodeJS.Timeout | undefined;
    private complete = false;
    private sawMatchEnd = false;
    private readonly reservedToMs: number;
    private readonly flushIntervalMs: number;
    private readonly batchSize: number;
    private readonly completeGraceMs: number;

    constructor(private readonly opts: MatchLifecycleManagerOptions) {
        this.reservedToMs = new Date(opts.booking.reserved_to).getTime();
        this.flushIntervalMs = opts.flushIntervalMs ?? DEFAULT_FLUSH_INTERVAL_MS;
        this.batchSize = opts.batchSize ?? DEFAULT_BATCH_SIZE;
        this.completeGraceMs = opts.completeGraceMs ?? DEFAULT_COMPLETE_GRACE_MS;
    }

    async start(): Promise<void> {
        let creds: CredentialsResponse;
        try {
            creds = await this.opts.webClient.fetchSignedJson<CredentialsResponse>(
                `/api/internal/match-servers/${this.opts.booking.server_id}/credentials`,
            );
        } catch (e) {
            // Hard failure at session open — emit synthetic manual_error.
            this.opts.logger.warn(
                { err: (e as Error).message, bookingId: this.opts.booking.id },
                'credentials fetch failed; emitting manual_error',
            );
            await this.emitManualError('unreachable', (e as Error).message);
            this.complete = true;
            return;
        }

        this.crcon = new CrconClient({
            url: `ws://${creds.host}:${creds.port_rcon}/ws/logs`,
            token: creds.api_token,
            logger: this.opts.logger,
            onLogs: (logs, _lastSeenId) => this.onLogs(logs),
            onError: (err) =>
                this.opts.logger.warn(
                    { err: err.message, bookingId: this.opts.booking.id },
                    'crcon error',
                ),
        });
        this.crcon.connect();

        this.flushTimer = setInterval(() => {
            void this.flush();
        }, this.flushIntervalMs);

        // Schedule tryComplete for reserved_to + grace window.
        const completeAt = Math.max(0, this.reservedToMs + this.completeGraceMs - Date.now());
        this.completeTimer = setTimeout(() => this.tryComplete(), completeAt);
    }

    stop(): void {
        if (this.flushTimer) {
            clearInterval(this.flushTimer);
            this.flushTimer = undefined;
        }
        if (this.completeTimer) {
            clearTimeout(this.completeTimer);
            this.completeTimer = undefined;
        }
        this.crcon?.close();
        this.crcon = undefined;
    }

    isComplete(): boolean {
        return this.complete;
    }

    /** Test seam — read the current buffer depth without flushing. */
    getBufferSize(): number {
        return this.buffer.length;
    }

    /** Test seam — read whether the manager has observed a match_end. */
    getSawMatchEnd(): boolean {
        return this.sawMatchEnd;
    }

    private onLogs(logs: unknown[]): void {
        for (const raw of logs) {
            // Duck-typed entry shape — CrconClient already zod-validated the frame
            // (LogFrameSchema). We pass each log entry through the normaliser; null
            // means "drop this action" and is silently skipped per Pitfall 3.
            const entry = raw as { id: string; log: Record<string, unknown> };
            const evt = normalise(entry);
            if (!evt) continue;
            this.buffer.push(evt);
            if (evt.event_type === 'match_end') {
                this.sawMatchEnd = true;
            }
        }
        if (this.buffer.length >= this.batchSize) {
            void this.flush();
        }
    }

    private async flush(): Promise<void> {
        if (this.buffer.length === 0) return;
        const batch = this.buffer.splice(0, this.buffer.length);

        let result;
        try {
            result = await this.opts.webClient.postEvents(this.opts.booking.match_id, batch);
        } catch (e) {
            // Treat throw as a non-2xx — queue for retry.
            this.opts.logger.warn(
                { err: (e as Error).message, matchId: this.opts.booking.match_id },
                'web ingest threw; queued for retry',
            );
            await this.opts.redis.lpush(
                queueKey(this.opts.booking.match_id),
                JSON.stringify(batch),
            );
            return;
        }

        if (result.status >= 200 && result.status < 300) return;

        // Non-2xx → push the entire batch onto the Redis failover queue.
        await this.opts.redis.lpush(
            queueKey(this.opts.booking.match_id),
            JSON.stringify(batch),
        );
        this.opts.logger.warn(
            {
                status: result.status,
                matchId: this.opts.booking.match_id,
                batchSize: batch.length,
            },
            'web ingest non-2xx; queued for retry',
        );
    }

    private async emitManualError(
        kind: 'unreachable' | 'auth_failed' | 'permission_denied',
        detail: string,
    ): Promise<void> {
        const evt: NormalisedEvent = {
            event_type: 'manual_error',
            crcon_action: 'SYNTHETIC',
            crcon_stream_id: `manual_error-${Date.now()}-${this.opts.booking.id}`,
            occurred_at: new Date().toISOString(),
            payload: { kind, detail },
        };

        try {
            await this.opts.webClient.postEvents(this.opts.booking.match_id, [evt]);
        } catch (e) {
            this.opts.logger.error(
                { err: (e as Error).message, matchId: this.opts.booking.match_id },
                'failed to emit manual_error',
            );
        }
    }

    private tryComplete(): void {
        if (!this.sawMatchEnd) {
            this.opts.logger.info(
                { matchId: this.opts.booking.match_id, bookingId: this.opts.booking.id },
                'booking elapsed without match_end',
            );
        }
        this.complete = true;
        this.stop();
    }
}
