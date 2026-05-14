// Plan 08-11 task 1 — BookingScheduler unit tests.
// Source: .planning/phases/08-rcon-automation/08-11-PLAN.md task 1 behaviour list.
//
// 5 cases:
//   1. tick() with empty due array → no managers spawned.
//   2. tick() with 2 due bookings → 2 managers in `this.active`.
//   3. tick() called twice with same bookings → managers NOT respawned (idempotent).
//   4. Manager marked complete → reaped from `this.active` on next tick.
//   5. webClient throws → logger.warn called; no crash.
//
// We mock WebIngestClient (via vi.fn) and supply a stub managerFactory that
// returns a controllable SchedulerManager — no real Redis / ws traffic in
// these unit tests.

import pino from 'pino';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { BookingScheduler } from '../../src/booking/BookingScheduler.js';
import type { SchedulerManager } from '../../src/booking/BookingScheduler.js';
import type { BookingDueData } from '../../src/booking/types.js';
import type { WebIngestClient } from '../../src/ingest/WebIngestClient.js';

const logger = pino({ level: 'silent' });

interface StubManager extends SchedulerManager {
    started: boolean;
    stopped: boolean;
    booking: BookingDueData;
    markComplete(): void;
}

function makeStubManager(booking: BookingDueData): StubManager {
    let complete = false;
    const m: StubManager = {
        booking,
        started: false,
        stopped: false,
        async start() {
            m.started = true;
        },
        stop() {
            m.stopped = true;
        },
        isComplete() {
            return complete;
        },
        markComplete() {
            complete = true;
        },
    };
    return m;
}

function makeBooking(id: string, matchId = `match-${id}`): BookingDueData {
    return {
        id,
        match_id: matchId,
        server_id: `server-${id}`,
        server_host: '127.0.0.1',
        server_port: 64000,
        reserved_from: new Date(Date.now() - 60_000).toISOString(),
        reserved_to: new Date(Date.now() + 3_600_000).toISOString(),
    };
}

function makeWebClientStub(dueRows: BookingDueData[] | Error): WebIngestClient {
    const fetchSignedJson = vi.fn(async () => {
        if (dueRows instanceof Error) throw dueRows;
        return dueRows;
    });
    return {
        fetchSignedJson,
        postEvents: vi.fn(),
    } as unknown as WebIngestClient;
}

describe('BookingScheduler', () => {
    let warnSpy: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
        warnSpy = vi.spyOn(logger, 'warn');
    });

    afterEach(() => {
        warnSpy.mockRestore();
        vi.restoreAllMocks();
    });

    it('tick() with empty due array spawns no managers', async () => {
        const webClient = makeWebClientStub([]);
        const created: StubManager[] = [];
        const scheduler = new BookingScheduler({
            webClient,
            logger,
            redis: {} as never,
            secret: 'x'.repeat(32),
            pollIntervalMs: 60_000,
            managerFactory: (booking) => {
                const m = makeStubManager(booking);
                created.push(m);
                return m;
            },
        });

        await scheduler.tick();
        expect(created).toHaveLength(0);
        expect(scheduler.getActiveCount()).toBe(0);
    });

    it('tick() with 2 due bookings spawns 2 managers and tracks them in this.active', async () => {
        const bookings = [makeBooking('A'), makeBooking('B')];
        const webClient = makeWebClientStub(bookings);
        const created: StubManager[] = [];
        const scheduler = new BookingScheduler({
            webClient,
            logger,
            redis: {} as never,
            secret: 'x'.repeat(32),
            pollIntervalMs: 60_000,
            managerFactory: (booking) => {
                const m = makeStubManager(booking);
                created.push(m);
                return m;
            },
        });

        await scheduler.tick();
        expect(created).toHaveLength(2);
        expect(created.every((m) => m.started)).toBe(true);
        expect(scheduler.getActiveCount()).toBe(2);
        expect(scheduler.isActive('A')).toBe(true);
        expect(scheduler.isActive('B')).toBe(true);
    });

    it('tick() called twice with same bookings does not respawn managers (idempotent)', async () => {
        const bookings = [makeBooking('A'), makeBooking('B')];
        const webClient = makeWebClientStub(bookings);
        const created: StubManager[] = [];
        const scheduler = new BookingScheduler({
            webClient,
            logger,
            redis: {} as never,
            secret: 'x'.repeat(32),
            pollIntervalMs: 60_000,
            managerFactory: (booking) => {
                const m = makeStubManager(booking);
                created.push(m);
                return m;
            },
        });

        await scheduler.tick();
        await scheduler.tick();

        expect(created).toHaveLength(2); // not 4
        expect(scheduler.getActiveCount()).toBe(2);
    });

    it('Manager marked complete is reaped from this.active on next tick', async () => {
        const bookings = [makeBooking('A')];
        const webClient = makeWebClientStub(bookings);
        const created: StubManager[] = [];
        const scheduler = new BookingScheduler({
            webClient,
            logger,
            redis: {} as never,
            secret: 'x'.repeat(32),
            pollIntervalMs: 60_000,
            managerFactory: (booking) => {
                const m = makeStubManager(booking);
                created.push(m);
                return m;
            },
        });

        await scheduler.tick();
        expect(scheduler.getActiveCount()).toBe(1);

        // Manager finishes naturally (e.g. match_end seen, or grace timer fires).
        created[0]!.markComplete();

        // Empty the due list so we exercise the reap path without a respawn
        // confusion. (Were the due list still to contain the booking id, the
        // scheduler would see it as already-active. With empty due rows AND
        // mgr.isComplete()===true, the only legal outcome is reap.)
        (webClient.fetchSignedJson as ReturnType<typeof vi.fn>).mockResolvedValueOnce([]);
        await scheduler.tick();

        expect(scheduler.getActiveCount()).toBe(0);
        expect(scheduler.isActive('A')).toBe(false);
        expect(created[0]!.stopped).toBe(true);
    });

    it('webClient throws → logger.warn called, scheduler does not crash', async () => {
        const webClient = makeWebClientStub(new Error('boom: network unreachable'));
        const created: StubManager[] = [];
        const scheduler = new BookingScheduler({
            webClient,
            logger,
            redis: {} as never,
            secret: 'x'.repeat(32),
            pollIntervalMs: 60_000,
            managerFactory: (booking) => {
                const m = makeStubManager(booking);
                created.push(m);
                return m;
            },
        });

        await expect(scheduler.tick()).resolves.toBeUndefined();
        expect(created).toHaveLength(0);
        expect(warnSpy).toHaveBeenCalledTimes(1);
        const warnArgs = warnSpy.mock.calls[0]!;
        expect(JSON.stringify(warnArgs)).toContain('boom: network unreachable');
    });
});
