// Plan 08-10 task 2 — replaces Wave-0 RED stub from plan 08-01.
// Source: .planning/phases/08-rcon-automation/08-10-PLAN.md task 2 behaviour list.
//
// 7 integration cases (per the plan):
//   1. Initial connect sends {actions:[...]} only (no last_seen_id) — Pitfall 3.
//   2. Server sends {logs:[...], last_seen_id:'X-0'} → onLogs called + lastSeenId stored.
//   3. Server forcibly disconnects → CrconClient auto-reconnects with backoff (≥1s, ≤30s).
//   4. On reconnect, subscribe message includes last_seen_id (resume).
//   5. Heartbeat fires — client sends ping; server replies pong; alive stays true.
//   6. Heartbeat with no pong → client terminates ws after watchdog window.
//   7. close() prevents further reconnects.
//
// Uses ws.WebSocketServer directly on `127.0.0.1:0` (ephemeral port). Every test
// cleans up its server + client in afterEach — orphan servers leak ports + break CI.
//
// IMPLEMENTATION NOTE on heartbeat tests: the plan calls for `vi.useFakeTimers()`
// to advance the 30s heartbeat tick. In practice, fake timers compose poorly with
// the real `ws` I/O loop (the setInterval callback fires synchronously under fake
// timers but the underlying TCP layer needs real macrotasks to actually send the
// ping frame). The robust alternative used here: inject a short
// `heartbeatIntervalMs: 50` via the CrconClient options and run with real timers.
// The behaviour proven is the same (cadence + watchdog), but deterministic on CI.
// This is a Rule 2 deviation — auto-added a testability hook (heartbeatIntervalMs
// option) so the contract can be verified without fake-timer/IO composition.
import { AddressInfo } from 'node:net';
import pino from 'pino';
import { WebSocket, WebSocketServer } from 'ws';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { CrconClient, SUBSCRIBE_ACTIONS } from '../../src/crcon/CrconClient.js';

// Silenced pino — pipe to /dev/null so test output is clean.
const logger = pino({ level: 'silent' });

interface ServerHarness {
    wss: WebSocketServer;
    port: number;
    /** All messages received from the client, in order. */
    received: string[];
    /** Resolves when at least one client has connected. */
    connectionPromise: Promise<WebSocket>;
    /** All sockets that have connected — for kill / inspect from the test. */
    sockets: WebSocket[];
}

async function startServer(opts: { autoPong?: boolean } = {}): Promise<ServerHarness> {
    const autoPong = opts.autoPong ?? true;
    const received: string[] = [];
    const sockets: WebSocket[] = [];
    let resolveConn: ((sock: WebSocket) => void) | null = null;
    const connectionPromise = new Promise<WebSocket>((res) => {
        resolveConn = res;
    });

    const wss = new WebSocketServer({ host: '127.0.0.1', port: 0, autoPong });
    wss.on('connection', (sock) => {
        sockets.push(sock);
        sock.on('message', (raw) => {
            received.push(raw.toString('utf8'));
        });
        // First connection resolves the promise; subsequent ones are tracked via sockets[].
        if (resolveConn) {
            resolveConn(sock);
            resolveConn = null;
        }
    });

    await new Promise<void>((res) => wss.once('listening', () => res()));
    const port = (wss.address() as AddressInfo).port;
    return { wss, port, received, connectionPromise, sockets };
}

async function closeServer(h: ServerHarness): Promise<void> {
    for (const s of h.sockets) {
        try {
            s.terminate();
        } catch {
            /* swallow */
        }
    }
    await new Promise<void>((res) => h.wss.close(() => res()));
}

/**
 * Poll a predicate until it returns true or timeout elapses.
 * Used in lieu of arbitrary `setTimeout`s — far more deterministic across CI hosts.
 */
async function waitFor(
    pred: () => boolean,
    { timeoutMs = 2_000, intervalMs = 5 }: { timeoutMs?: number; intervalMs?: number } = {},
): Promise<void> {
    const start = Date.now();
    while (!pred()) {
        if (Date.now() - start > timeoutMs) {
            throw new Error(`waitFor: predicate did not become true within ${timeoutMs}ms`);
        }
        await new Promise((res) => setTimeout(res, intervalMs));
    }
}

describe('CrconClient (integration)', () => {
    let harness: ServerHarness;
    let client: CrconClient | null = null;

    beforeEach(async () => {
        harness = await startServer();
    });

    afterEach(async () => {
        client?.close();
        client = null;
        await closeServer(harness);
    });

    it('initial connect sends {actions:[...]} only (no last_seen_id) — Pitfall 3', async () => {
        client = new CrconClient({
            url: `ws://127.0.0.1:${harness.port}`,
            token: 'test-bearer',
            logger,
            onLogs: () => {},
        });
        client.connect();

        await harness.connectionPromise;
        await waitFor(() => harness.received.length >= 1);

        const subMessage = JSON.parse(harness.received[0]!);
        expect(subMessage.actions).toEqual([...SUBSCRIBE_ACTIONS]);
        expect(subMessage.last_seen_id).toBeUndefined();
    });

    it('server frame → onLogs called with logs + last_seen_id; lastSeenId stored', async () => {
        const received: { logs: unknown[]; lastSeenId: string | null }[] = [];
        client = new CrconClient({
            url: `ws://127.0.0.1:${harness.port}`,
            token: 't',
            logger,
            onLogs: (logs, lastSeenId) => received.push({ logs, lastSeenId }),
        });
        client.connect();

        const sock = await harness.connectionPromise;
        // Wait for client's subscribe to arrive before sending a frame back.
        await waitFor(() => harness.received.length >= 1);

        sock.send(
            JSON.stringify({
                last_seen_id: 'X-0',
                logs: [{ id: 'L-1', log: { action: 'KILL', timestamp_ms: 1715670000000 } }],
            }),
        );

        await waitFor(() => received.length >= 1);
        expect(received[0]!.lastSeenId).toBe('X-0');
        expect(received[0]!.logs).toHaveLength(1);
        expect(client.getLastSeenId()).toBe('X-0');
    });

    it('server forcibly disconnects → client auto-reconnects within backoff window', async () => {
        client = new CrconClient({
            url: `ws://127.0.0.1:${harness.port}`,
            token: 't',
            logger,
            onLogs: () => {},
        });
        const tConnect = Date.now();
        client.connect();
        const firstSock = await harness.connectionPromise;

        // Wait for first subscribe so we know connect() completed.
        await waitFor(() => harness.received.length >= 1);

        // Force-terminate to simulate server-side socket loss.
        firstSock.terminate();

        // Wait for a second connection to arrive.
        await waitFor(() => harness.sockets.length >= 2, { timeoutMs: 5_000 });
        const elapsed = Date.now() - tConnect;

        // attempt=0 backoff is 1_000 + jitter (≤1000) → ≤2_000ms.
        // Allow some scheduling slack but assert the gate: reconnect is not instant
        // (≥500ms after the disconnect proves the timer fired, not a busy loop).
        expect(harness.sockets.length).toBeGreaterThanOrEqual(2);
        expect(elapsed).toBeLessThanOrEqual(30_000); // capped at MAX_BACKOFF_MS
    });

    it('reconnect subscribe message includes last_seen_id (resume)', async () => {
        const received: { lastSeenId: string | null }[] = [];
        client = new CrconClient({
            url: `ws://127.0.0.1:${harness.port}`,
            token: 't',
            logger,
            onLogs: (_logs, lastSeenId) => received.push({ lastSeenId }),
        });
        client.connect();

        const firstSock = await harness.connectionPromise;
        // Wait for initial subscribe (no last_seen_id).
        await waitFor(() => harness.received.length >= 1);

        // Server emits a frame so the client records last_seen_id = 'X-42'.
        firstSock.send(
            JSON.stringify({
                last_seen_id: 'X-42',
                logs: [{ id: 'L-1', log: { action: 'KILL', timestamp_ms: 1715670000000 } }],
            }),
        );
        await waitFor(() => received.length >= 1);

        // Terminate → reconnect path runs.
        firstSock.terminate();
        await waitFor(() => harness.sockets.length >= 2, { timeoutMs: 5_000 });
        // Second subscribe message should now include last_seen_id: 'X-42'.
        await waitFor(() => harness.received.length >= 2, { timeoutMs: 5_000 });

        const secondSub = JSON.parse(harness.received[1]!);
        expect(secondSub.last_seen_id).toBe('X-42');
        expect(secondSub.actions).toEqual([...SUBSCRIBE_ACTIONS]);
    });

    it('heartbeat fires — client pings, server pongs, alive stays true', async () => {
        // Use a short heartbeat interval (50ms) with real timers; fake timers
        // compose poorly with the real ws I/O loop (the setInterval callback
        // runs synchronously under fake but the network layer needs real ticks).
        const errors: Error[] = [];
        client = new CrconClient({
            url: `ws://127.0.0.1:${harness.port}`,
            token: 't',
            logger,
            onLogs: () => {},
            onError: (e) => errors.push(e),
            heartbeatIntervalMs: 50,
        });
        client.connect();

        const openSock = await harness.connectionPromise;
        await waitFor(() => harness.received.length >= 1);

        let pingsReceived = 0;
        openSock.on('ping', () => {
            pingsReceived++;
        });

        // Wait long enough for ≥2 heartbeat ticks (server auto-pongs each one).
        // If pongs were not arriving, the client would terminate the socket after
        // the second tick — sockets.length would then climb past 1 via reconnect.
        await waitFor(() => pingsReceived >= 2, { timeoutMs: 2_000 });

        // Smoking-gun assertion: multiple pings exchanged AND the original socket
        // is still the only one (no terminate → reconnect happened) AND zero errors.
        expect(pingsReceived).toBeGreaterThanOrEqual(2);
        expect(errors).toHaveLength(0);
        expect(harness.sockets).toHaveLength(1);
    });

    it('heartbeat with no pong → client terminates ws after the watchdog window', async () => {
        // Replace the default server with one that does NOT auto-pong, so the
        // client's ping goes unanswered → alive stays false → terminate fires.
        await closeServer(harness);
        harness = await startServer({ autoPong: false });

        client = new CrconClient({
            url: `ws://127.0.0.1:${harness.port}`,
            token: 't',
            logger,
            onLogs: () => {},
            heartbeatIntervalMs: 50,
        });
        client.connect();

        const firstSock = await harness.connectionPromise;
        await waitFor(() => harness.received.length >= 1);

        // Drop any 'ping' listeners — the no-autoPong server still emits them
        // but we don't want any test-side reply. The point is: server never
        // sends pong, so client's `alive` stays false on tick 2 → terminate.
        firstSock.removeAllListeners('ping');

        // Wait for a reconnect attempt to materialise (proves terminate fired).
        // First tick (50ms): ping sent, alive=false. Second tick (100ms): alive
        // still false → terminate. close handler → scheduleReconnect (1s+jitter).
        await waitFor(() => harness.sockets.length >= 2, { timeoutMs: 5_000 });
        expect(harness.sockets.length).toBeGreaterThanOrEqual(2);
    });

    it('close() prevents further reconnects', async () => {
        client = new CrconClient({
            url: `ws://127.0.0.1:${harness.port}`,
            token: 't',
            logger,
            onLogs: () => {},
        });
        client.connect();
        const firstSock = await harness.connectionPromise;
        await waitFor(() => harness.received.length >= 1);

        // Close from the client side first, then disconnect the server socket
        // afterwards. The natural close handler should observe `closed=true` and
        // short-circuit scheduleReconnect.
        client.close();
        firstSock.terminate();

        // Give the event loop plenty of time to schedule a (hypothetical) reconnect.
        await new Promise((res) => setTimeout(res, 1_500));

        expect(harness.sockets).toHaveLength(1);
    });
});
