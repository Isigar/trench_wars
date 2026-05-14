// Plan 08-11 task 1 — BookingScheduler polls /api/internal/bookings/due every
// POLL_INTERVAL_MS, spawns one MatchLifecycleManager per due booking, and reaps
// completed managers. Source: 08-11-PLAN.md <interfaces> BookingScheduler.
//
// Threat refs:
//   - T-08-11-03 (worker scaled out replicas claim same booking): the
//     per-process `this.active` Map prevents same-replica duplicate spawn.
//     Cross-replica defence is the D-014 / Pitfall 11 single-replica deployment.
//
// Test seams:
//   - `managerFactory` option lets unit tests inject a stub manager (no real
//     CRCON ws / Redis traffic). Production wiring (index.ts) uses the default
//     factory that builds a real MatchLifecycleManager.

import type { Redis } from 'ioredis';
import type { Logger } from 'pino';
import type { WebIngestClient } from '../ingest/WebIngestClient.js';
import type { BookingDueData } from './types.js';
import { MatchLifecycleManager } from './MatchLifecycleManager.js';

/**
 * Abstract over MatchLifecycleManager so tests can substitute a stub.
 * Production code (index.ts) leaves managerFactory unset and gets the real
 * MatchLifecycleManager.
 */
export interface SchedulerManager {
    start(): Promise<void>;
    stop(): void;
    isComplete(): boolean;
}

export interface BookingSchedulerOptions {
    webClient: WebIngestClient;
    logger: Logger;
    redis: Redis;
    /** Worker secret — passed through to spawned MatchLifecycleManagers. */
    secret: string;
    /** Poll cadence (ms). Default 30_000 per config.ts. */
    pollIntervalMs: number;
    /**
     * Factory for spawning per-booking managers. Defaults to the real
     * MatchLifecycleManager constructor; tests inject a stub.
     */
    managerFactory?: (booking: BookingDueData) => SchedulerManager;
}

/**
 * BookingScheduler ticks every pollIntervalMs:
 *   1. GET /api/internal/bookings/due (HMAC-signed).
 *   2. For each row not already in `this.active`, spawn a MatchLifecycleManager.
 *   3. Reap completed managers from `this.active`.
 *
 * Errors from the poll itself (network / 5xx / malformed JSON) are logged
 * via logger.warn — the scheduler does NOT crash on a transient web outage.
 * The next tick retries; meanwhile already-spawned managers keep streaming.
 */
export class BookingScheduler {
    private active = new Map<string, SchedulerManager>();
    private timer: NodeJS.Timeout | undefined;
    private readonly factory: (booking: BookingDueData) => SchedulerManager;

    constructor(private readonly opts: BookingSchedulerOptions) {
        this.factory =
            opts.managerFactory ??
            ((booking) =>
                new MatchLifecycleManager({
                    booking,
                    webClient: opts.webClient,
                    logger: opts.logger,
                    redis: opts.redis,
                    secret: opts.secret,
                }));
    }

    start(): void {
        this.timer = setInterval(() => {
            void this.tick();
        }, this.opts.pollIntervalMs);
        // Immediate first poll so tests + boot get fast feedback.
        void this.tick();
    }

    stop(): void {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = undefined;
        }
        for (const mgr of this.active.values()) {
            mgr.stop();
        }
        this.active.clear();
    }

    /** Read-only view onto the active booking set — useful for tests + diagnostics. */
    getActiveCount(): number {
        return this.active.size;
    }

    /** True when a booking id is currently being managed. Test seam. */
    isActive(bookingId: string): boolean {
        return this.active.has(bookingId);
    }

    async tick(): Promise<void> {
        let due: BookingDueData[];
        try {
            due = await this.opts.webClient.fetchSignedJson<BookingDueData[]>(
                '/api/internal/bookings/due',
            );
        } catch (e) {
            this.opts.logger.warn({ err: (e as Error).message }, 'booking poll failed');
            return;
        }

        // Spawn missing managers — idempotent per booking id.
        for (const booking of due) {
            if (this.active.has(booking.id)) continue;
            const mgr = this.factory(booking);
            this.active.set(booking.id, mgr);
            try {
                await mgr.start();
            } catch (e) {
                this.opts.logger.warn(
                    { err: (e as Error).message, bookingId: booking.id },
                    'manager start failed',
                );
                // Best-effort cleanup — manager.start() should be self-contained,
                // but if it throws synchronously we drop the half-initialised
                // entry so the next tick can retry.
                this.active.delete(booking.id);
            }
        }

        // Reap completed managers.
        for (const [bookingId, mgr] of this.active.entries()) {
            if (mgr.isComplete()) {
                mgr.stop();
                this.active.delete(bookingId);
            }
        }
    }
}
