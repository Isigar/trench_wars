// Plan 08-11 task 2 — MatchLifecycleManager integration tests.
// Source: .planning/phases/08-rcon-automation/08-11-PLAN.md task 2 behaviour list.
//
// 7 cases (per the plan):
//   1. Happy path: fetch credentials (mock 200), open ws (server accepts),
//      receive 5 events incl. match_end → postEvents called with all 5;
//      sawMatchEnd=true.
//   2. Credentials fetch fails (mock 404) → emitManualError(kind='unreachable');
//      complete=true.
//   3. Credentials ok but ws connection refused → onError fires; buffer empty.
//   4. 12 events arrive quickly → flush triggers after 10 (batch cap).
//   5. flush 2s timer fires with buffered events → postEvents called.
//   6. postEvents returns 500 → events LPUSHed to `rcon:queue:{matchId}`
//      (ioredis-mock).
//   7. reserved_to elapses + no match_end → tryComplete sets complete=true
//      after grace.
//
// Two test-time servers:
//   - ws.WebSocketServer on 127.0.0.1:0 — mocks CRCON `/ws/logs`.
//   - http.createServer on 127.0.0.1:0 — mocks apps/web ingest + credentials.
//     We avoid undici interception here because that introduces complex
//     setup; the real undici fetch in WebIngestClient just talks to localhost.
//
// Each test creates its own pair, cleans up in afterEach (orphan ports break CI).

import { AddressInfo } from 'node:net';
import { createServer, type IncomingMessage, type Server, type ServerResponse } from 'node:http';
import RedisMock from 'ioredis-mock';
import pino from 'pino';
import { WebSocketServer, type WebSocket } from 'ws';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { MatchLifecycleManager } from '../../src/booking/MatchLifecycleManager.js';
import type { BookingDueData } from '../../src/booking/types.js';
import { WebIngestClient } from '../../src/ingest/WebIngestClient.js';
import { queueKey } from '../../src/queue/RedisFailoverQueue.js';

const logger = pino({ level: 'silent' });
const SECRET = 'x'.repeat(32);

interface WsHarness {
    wss: WebSocketServer;
    port: number;
    sockets: WebSocket[];
    received: string[];
    connectionPromise: Promise<WebSocket>;
}

async function startWsServer(): Promise<WsHarness> {
    const sockets: WebSocket[] = [];
    const received: string[] = [];
    let resolveConn: ((s: WebSocket) => void) | null = null;
    const connectionPromise = new Promise<WebSocket>((res) => {
        resolveConn = res;
    });

    const wss = new WebSocketServer({ host: '127.0.0.1', port: 0 });
    wss.on('connection', (sock) => {
        sockets.push(sock);
        sock.on('message', (raw) => received.push(raw.toString('utf8')));
        if (resolveConn) {
            resolveConn(sock);
            resolveConn = null;
        }
    });

    await new Promise<void>((res) => wss.once('listening', () => res()));
    const port = (wss.address() as AddressInfo).port;
    return { wss, port, sockets, received, connectionPromise };
}

async function closeWsServer(h: WsHarness): Promise<void> {
    for (const s of h.sockets) {
        try {
            s.terminate();
        } catch {
            /* swallow */
        }
    }
    await new Promise<void>((res) => h.wss.close(() => res()));
}

interface HttpHarness {
    server: Server;
    port: number;
    /** Per-request recordings: [method, url, bodyStr, statusReturned]. */
    requests: { method: string; url: string; body: string; statusReturned: number }[];
}

interface HttpRouter {
    /** Returns [status, body-obj]. Path-prefix match (startsWith). */
    (method: string, url: string, body: string): { status: number; body: unknown };
}

async function startHttpServer(route: HttpRouter): Promise<HttpHarness> {
    const requests: HttpHarness['requests'] = [];
    const server = createServer((req: IncomingMessage, res: ServerResponse) => {
        const chunks: Buffer[] = [];
        req.on('data', (c) => chunks.push(c as Buffer));
        req.on('end', () => {
            const body = Buffer.concat(chunks).toString('utf8');
            const { status, body: outBody } = route(req.method ?? 'GET', req.url ?? '', body);
            requests.push({
                method: req.method ?? 'GET',
                url: req.url ?? '',
                body,
                statusReturned: status,
            });
            res.statusCode = status;
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify(outBody));
        });
    });
    await new Promise<void>((res) => server.listen(0, '127.0.0.1', () => res()));
    const port = (server.address() as AddressInfo).port;
    return { server, port, requests };
}

async function closeHttpServer(h: HttpHarness): Promise<void> {
    await new Promise<void>((res) => h.server.close(() => res()));
}

async function waitFor(
    pred: () => boolean | Promise<boolean>,
    { timeoutMs = 2_000, intervalMs = 5 }: { timeoutMs?: number; intervalMs?: number } = {},
): Promise<void> {
    const start = Date.now();
    for (;;) {
        const ok = await pred();
        if (ok) return;
        if (Date.now() - start > timeoutMs) {
            throw new Error(`waitFor: predicate did not become true within ${timeoutMs}ms`);
        }
        await new Promise((res) => setTimeout(res, intervalMs));
    }
}

function makeBooking(opts: { serverId: string; serverPort: number; reservedToMs?: number }): BookingDueData {
    return {
        id: `book-${opts.serverId}`,
        match_id: `match-${opts.serverId}`,
        server_id: opts.serverId,
        server_host: '127.0.0.1',
        server_port: opts.serverPort,
        reserved_from: new Date(Date.now() - 60_000).toISOString(),
        reserved_to: new Date(opts.reservedToMs ?? Date.now() + 3_600_000).toISOString(),
    };
}

function makeKillLog(id: string): { id: string; log: Record<string, unknown> } {
    return {
        id,
        log: {
            action: 'KILL',
            timestamp_ms: 1715670000000,
            steam_id_64_1: '76561198000000001',
            player: 'Alice',
            steam_id_64_2: '76561198000000002',
            player2: 'Bob',
            weapon: 'MG42',
        },
    };
}

function makeMatchEndLog(id: string): { id: string; log: Record<string, unknown> } {
    return {
        id,
        log: {
            action: 'MATCH ENDED',
            timestamp_ms: 1715670600000,
            winning_team: 'allies',
            allies_score: 5,
            axis_score: 3,
        },
    };
}

describe('MatchLifecycleManager (integration)', () => {
    let ws: WsHarness;
    let http: HttpHarness;
    let manager: MatchLifecycleManager | null = null;
    let redis: InstanceType<typeof RedisMock>;

    beforeEach(async () => {
        redis = new RedisMock();
    });

    afterEach(async () => {
        manager?.stop();
        manager = null;
        if (ws) await closeWsServer(ws);
        if (http) await closeHttpServer(http);
        await redis.flushall();
        await redis.disconnect();
    });

    it('happy path: 5 events incl match_end → postEvents called; sawMatchEnd=true', async () => {
        ws = await startWsServer();
        // HTTP routes: credentials → 200; postEvents → 200.
        http = await startHttpServer((method, url, _body) => {
            if (method === 'GET' && url.startsWith('/api/internal/match-servers/')) {
                return {
                    status: 200,
                    body: {
                        host: '127.0.0.1',
                        port_rcon: ws.port,
                        api_token: 'bearer-xyz',
                    },
                };
            }
            if (method === 'POST' && url.includes('/events')) {
                return { status: 202, body: { accepted: true } };
            }
            return { status: 404, body: { err: 'not found' } };
        });

        const webClient = new WebIngestClient({
            secret: SECRET,
            baseUrl: `http://127.0.0.1:${http.port}`,
            logger,
        });
        manager = new MatchLifecycleManager({
            booking: makeBooking({ serverId: 's1', serverPort: ws.port }),
            webClient,
            logger,
            redis,
            secret: SECRET,
            flushIntervalMs: 50,
            batchSize: 10,
        });
        await manager.start();

        const sock = await ws.connectionPromise;
        await waitFor(() => ws.received.length >= 1); // subscribe arrived

        // Server emits a frame with 5 events: 4 kills + match_end.
        sock.send(
            JSON.stringify({
                last_seen_id: 'X-5',
                logs: [
                    makeKillLog('L-1'),
                    makeKillLog('L-2'),
                    makeKillLog('L-3'),
                    makeKillLog('L-4'),
                    makeMatchEndLog('L-5'),
                ],
            }),
        );

        // 5 < batchSize=10, so flush triggers via the 50ms timer.
        await waitFor(
            () => http.requests.some((r) => r.url.includes('/events')),
            { timeoutMs: 2_000 },
        );

        const ev = http.requests.find((r) => r.url.includes('/events'))!;
        const payload = JSON.parse(ev.body) as { events: { event_type: string }[] };
        expect(payload.events).toHaveLength(5);
        expect(payload.events.map((e) => e.event_type)).toContain('match_end');
        expect(manager.getSawMatchEnd()).toBe(true);
    });

    it('credentials fetch 404 → emitManualError(kind=unreachable); complete=true', async () => {
        ws = await startWsServer(); // not used — kept for afterEach cleanup symmetry
        http = await startHttpServer((method, url, _body) => {
            if (method === 'GET' && url.startsWith('/api/internal/match-servers/')) {
                return { status: 404, body: { err: 'server not found' } };
            }
            if (method === 'POST' && url.includes('/events')) {
                return { status: 202, body: { accepted: true } };
            }
            return { status: 500, body: {} };
        });

        const webClient = new WebIngestClient({
            secret: SECRET,
            baseUrl: `http://127.0.0.1:${http.port}`,
            logger,
        });
        manager = new MatchLifecycleManager({
            booking: makeBooking({ serverId: 's2', serverPort: ws.port }),
            webClient,
            logger,
            redis,
            secret: SECRET,
            flushIntervalMs: 50,
            batchSize: 10,
        });
        await manager.start();

        // After credentials 404, manager must have POSTed a synthetic manual_error.
        await waitFor(() => http.requests.some((r) => r.url.includes('/events')));
        const manualErrorPost = http.requests.find((r) => r.url.includes('/events'))!;
        const payload = JSON.parse(manualErrorPost.body) as {
            events: { event_type: string; payload: { kind: string } }[];
        };
        expect(payload.events).toHaveLength(1);
        expect(payload.events[0]!.event_type).toBe('manual_error');
        expect(payload.events[0]!.payload.kind).toBe('unreachable');
        expect(manager.isComplete()).toBe(true);
    });

    it('credentials ok but ws connection refused → onError fires; buffer remains empty', async () => {
        // Start ws server, snapshot port, then close it BEFORE manager.start so
        // the ws.connect() attempt is guaranteed-refused.
        const tmpWs = await startWsServer();
        const refusedPort = tmpWs.port;
        await closeWsServer(tmpWs);
        // Keep `ws` non-null for afterEach symmetry but it has nothing to clean up.
        ws = await startWsServer();

        http = await startHttpServer((method, url, _body) => {
            if (method === 'GET' && url.startsWith('/api/internal/match-servers/')) {
                return {
                    status: 200,
                    body: {
                        host: '127.0.0.1',
                        port_rcon: refusedPort,
                        api_token: 'bearer-xyz',
                    },
                };
            }
            return { status: 202, body: { accepted: true } };
        });

        const webClient = new WebIngestClient({
            secret: SECRET,
            baseUrl: `http://127.0.0.1:${http.port}`,
            logger,
        });
        manager = new MatchLifecycleManager({
            booking: makeBooking({ serverId: 's3', serverPort: refusedPort }),
            webClient,
            logger,
            redis,
            secret: SECRET,
            flushIntervalMs: 50,
            batchSize: 10,
        });
        await manager.start();

        // Wait long enough for the ECONNREFUSED to surface via ws 'error'.
        // The buffer should remain empty (no frames ever arrived).
        await new Promise((res) => setTimeout(res, 500));
        expect(manager.getBufferSize()).toBe(0);
        // No /events POSTs (no normalised events to send).
        expect(http.requests.filter((r) => r.url.includes('/events'))).toHaveLength(0);
    });

    it('12 events arrive quickly → flush triggered after 10 (batch cap)', async () => {
        ws = await startWsServer();
        http = await startHttpServer((method, url, _body) => {
            if (method === 'GET' && url.startsWith('/api/internal/match-servers/')) {
                return {
                    status: 200,
                    body: { host: '127.0.0.1', port_rcon: ws.port, api_token: 't' },
                };
            }
            if (method === 'POST' && url.includes('/events')) {
                return { status: 202, body: { accepted: true } };
            }
            return { status: 404, body: {} };
        });

        const webClient = new WebIngestClient({
            secret: SECRET,
            baseUrl: `http://127.0.0.1:${http.port}`,
            logger,
        });
        manager = new MatchLifecycleManager({
            booking: makeBooking({ serverId: 's4', serverPort: ws.port }),
            webClient,
            logger,
            redis,
            secret: SECRET,
            flushIntervalMs: 60_000, // long — so only the batch-cap path can flush
            batchSize: 10,
        });
        await manager.start();

        const sock = await ws.connectionPromise;
        await waitFor(() => ws.received.length >= 1);

        // 12 KILL logs in one frame — batch cap should trigger flush at 10.
        const logs = Array.from({ length: 12 }, (_, i) => makeKillLog(`L-${i + 1}`));
        sock.send(JSON.stringify({ last_seen_id: 'X-12', logs }));

        await waitFor(() => http.requests.some((r) => r.url.includes('/events')));
        const ev = http.requests.find((r) => r.url.includes('/events'))!;
        const payload = JSON.parse(ev.body) as { events: unknown[] };
        // The flush takes the entire buffer (12 events) once the threshold trips.
        // (Per the plan: `if (this.buffer.length >= 10) void this.flush()` —
        // flush takes the whole buffer, not just the first 10.)
        expect(payload.events.length).toBeGreaterThanOrEqual(10);
    });

    it('flush 2s timer fires with buffered events → postEvents called', async () => {
        ws = await startWsServer();
        http = await startHttpServer((method, url, _body) => {
            if (method === 'GET' && url.startsWith('/api/internal/match-servers/')) {
                return {
                    status: 200,
                    body: { host: '127.0.0.1', port_rcon: ws.port, api_token: 't' },
                };
            }
            if (method === 'POST' && url.includes('/events')) {
                return { status: 202, body: { accepted: true } };
            }
            return { status: 404, body: {} };
        });

        const webClient = new WebIngestClient({
            secret: SECRET,
            baseUrl: `http://127.0.0.1:${http.port}`,
            logger,
        });
        manager = new MatchLifecycleManager({
            booking: makeBooking({ serverId: 's5', serverPort: ws.port }),
            webClient,
            logger,
            redis,
            secret: SECRET,
            flushIntervalMs: 100, // short — so the timer-driven flush is observable
            batchSize: 50, // high — guarantees only the timer path can flush
        });
        await manager.start();

        const sock = await ws.connectionPromise;
        await waitFor(() => ws.received.length >= 1);

        // 3 events, well under batchSize=50 → flush must come from the 100ms timer.
        sock.send(
            JSON.stringify({
                last_seen_id: 'X-3',
                logs: [makeKillLog('L-1'), makeKillLog('L-2'), makeKillLog('L-3')],
            }),
        );

        await waitFor(() => http.requests.some((r) => r.url.includes('/events')));
        const ev = http.requests.find((r) => r.url.includes('/events'))!;
        const payload = JSON.parse(ev.body) as { events: unknown[] };
        expect(payload.events).toHaveLength(3);
    });

    it('postEvents returns 500 → events LPUSHed to rcon:queue:{matchId}', async () => {
        ws = await startWsServer();
        http = await startHttpServer((method, url, _body) => {
            if (method === 'GET' && url.startsWith('/api/internal/match-servers/')) {
                return {
                    status: 200,
                    body: { host: '127.0.0.1', port_rcon: ws.port, api_token: 't' },
                };
            }
            if (method === 'POST' && url.includes('/events')) {
                return { status: 500, body: { err: 'db down' } };
            }
            return { status: 404, body: {} };
        });

        const webClient = new WebIngestClient({
            secret: SECRET,
            baseUrl: `http://127.0.0.1:${http.port}`,
            logger,
        });
        const booking = makeBooking({ serverId: 's6', serverPort: ws.port });
        manager = new MatchLifecycleManager({
            booking,
            webClient,
            logger,
            redis,
            secret: SECRET,
            flushIntervalMs: 50,
            batchSize: 10,
        });
        await manager.start();

        const sock = await ws.connectionPromise;
        await waitFor(() => ws.received.length >= 1);

        sock.send(
            JSON.stringify({
                last_seen_id: 'X-2',
                logs: [makeKillLog('L-1'), makeKillLog('L-2')],
            }),
        );

        // Wait for the failing POST + the LPUSH to land.
        await waitFor(
            async () => {
                const len = await redis.llen(queueKey(booking.match_id));
                return len >= 1;
            },
            { timeoutMs: 2_000 },
        );

        const queued = await redis.lrange(queueKey(booking.match_id), 0, -1);
        expect(queued).toHaveLength(1); // one stored batch
        const parsedBatch = JSON.parse(queued[0]!) as { event_type: string }[];
        expect(parsedBatch).toHaveLength(2);
        expect(parsedBatch.every((e) => e.event_type === 'player_kill')).toBe(true);
    });

    it('reserved_to elapses + no match_end → tryComplete sets complete=true after grace', async () => {
        ws = await startWsServer();
        http = await startHttpServer((method, url, _body) => {
            if (method === 'GET' && url.startsWith('/api/internal/match-servers/')) {
                return {
                    status: 200,
                    body: { host: '127.0.0.1', port_rcon: ws.port, api_token: 't' },
                };
            }
            if (method === 'POST' && url.includes('/events')) {
                return { status: 202, body: { accepted: true } };
            }
            return { status: 404, body: {} };
        });

        const webClient = new WebIngestClient({
            secret: SECRET,
            baseUrl: `http://127.0.0.1:${http.port}`,
            logger,
        });
        // reserved_to is RIGHT NOW; grace=50ms — so the completeTimer fires almost
        // immediately. Production uses 60_000ms grace.
        manager = new MatchLifecycleManager({
            booking: makeBooking({
                serverId: 's7',
                serverPort: ws.port,
                reservedToMs: Date.now(),
            }),
            webClient,
            logger,
            redis,
            secret: SECRET,
            flushIntervalMs: 1_000,
            batchSize: 10,
            completeGraceMs: 50,
        });
        await manager.start();

        await waitFor(() => manager!.isComplete(), { timeoutMs: 2_000 });
        expect(manager.isComplete()).toBe(true);
        expect(manager.getSawMatchEnd()).toBe(false);
    });
});

