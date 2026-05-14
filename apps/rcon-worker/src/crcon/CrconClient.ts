// Plan 08-10 task 2 — replaces the Wave-0 skeleton (file did not exist).
// Source: .planning/phases/08-rcon-automation/08-10-PLAN.md <interfaces> + must_haves.
// Source (patterns): 08-RESEARCH.md Pattern 1 (reconnect with backoff + jitter +
//   last_seen_id resume) + Pattern 2 (30s ping + 10s pong-watchdog heartbeat) +
//   Pitfall 3 (initial connect on new booking sends {actions:[...]} only — no
//   last_seen_id — to avoid replaying hours of stale logs).
//
// Threat refs (08-10 register):
//   - T-08-10-02 (worker accepts forged ws frames): LogFrameSchema zod-validates
//     every inbound frame; parse failures surface via onError without dropping
//     the connection (graceful degradation — reconnect handles persistent
//     mismatches).
//   - T-08-10-03 (tight reconnect loop floods CRCON): exponential backoff with
//     jitter, capped at 30s.
//   - T-08-10-04 (half-open dead TCP exhausts FDs): 30s ping + 10s pong
//     watchdog terminates the socket promptly.
//   - T-08-10-05 (PII in worker logs): logger redact list set in
//     src/logging/logger.ts — this file emits only counts + ids, never raw
//     steam_id_64 / player names.

import { WebSocket } from 'ws';
import { LogFrameSchema } from './types.js';
import type { Logger } from 'pino';

/**
 * The 7 CRCON actions the worker subscribes to.
 *
 * Matches CrconEventNormaliser.normalise() switch arms 1:1 — every action here
 * has a canonical event_type mapping, and every other CRCON action (CHAT, ADMIN
 * COMMAND, etc.) returns null from normalise and is silently dropped.
 */
export const SUBSCRIBE_ACTIONS = [
    'MATCH START',
    'MATCH ENDED',
    'KILL',
    'TEAM KILL',
    'CONNECTED',
    'DISCONNECTED',
    'TEAMSWITCH',
] as const;

/** Backoff schedule cap — RESEARCH Pattern 1 (30s ceiling for retry intervals). */
const MAX_BACKOFF_MS = 30_000;

/** Heartbeat interval — RESEARCH Pattern 2 (30s ping cadence). */
const HEARTBEAT_INTERVAL_MS = 30_000;

/** Handshake timeout for the initial ws upgrade. */
const HANDSHAKE_TIMEOUT_MS = 10_000;

export interface CrconClientOptions {
    /** CRCON `/ws/logs` URL — `ws://host:port/ws/logs` (or `wss://` in prod). */
    url: string;
    /** Bearer token for the Authorization header. NEVER logged. */
    token: string;
    /** Pino logger (redact list pre-configured in src/logging/logger.ts). */
    logger: Logger;
    /**
     * Called with each batch of raw CRCON log entries. CrconEventNormaliser
     * runs downstream — this callback is the bridge to the booking scheduler
     * (plan 08-11) which fans events into per-match queues.
     */
    onLogs: (logs: unknown[], lastSeenId: string | null) => void;
    /** Optional error sink — receives ws errors + JSON / zod parse failures. */
    onError?: (err: Error) => void;
    /**
     * Optional override for the 30s heartbeat tick (Pattern 2). Production
     * leaves this unset to use HEARTBEAT_INTERVAL_MS=30_000. Tests override
     * with a short interval (e.g. 50ms) to exercise the ping/pong + watchdog
     * paths deterministically without fake timers (fake timers + real ws I/O
     * compose poorly because the underlying socket schedules on real
     * macrotasks).
     */
    heartbeatIntervalMs?: number;
}

/**
 * Thin wrapper around `ws` for CRCON `/ws/logs` consumption.
 *
 * Lifecycle:
 *   connect() → ws.onopen sends {actions: [...]} OR {last_seen_id, actions: [...]}
 *             → ws.onmessage parses LogFrameSchema → onLogs(logs, last_seen_id)
 *             → on close, scheduleReconnect() backs off (≥1s, ≤30s, jittered)
 *             → close() prevents further reconnects + terminates the socket.
 *
 * Heartbeat:
 *   On open, start a 30s ping interval. Each interval, if `alive` is still
 *   false from the previous tick (no pong received in the 30s window), the
 *   socket is terminated and the natural close → scheduleReconnect path kicks in.
 *   On pong, alive flips back to true.
 *
 * Resume:
 *   `lastSeenId` is updated every time the server emits a frame. On reconnect,
 *   the next subscribe message includes `last_seen_id` so CRCON replays from
 *   that cursor. First-time-on-new-booking sends NO `last_seen_id` to avoid
 *   replaying hours of stale logs (Pitfall 3).
 */
export class CrconClient {
    private ws: WebSocket | null = null;
    private lastSeenId: string | null = null;
    private attempt = 0;
    private heartbeatTimer: NodeJS.Timeout | undefined;
    private reconnectTimer: NodeJS.Timeout | undefined;
    private alive = true;
    private closed = false;

    constructor(private readonly opts: CrconClientOptions) {}

    /** Open the ws connection. Idempotent against closed() — no-op if closed. */
    connect(): void {
        if (this.closed) return;

        this.ws = new WebSocket(this.opts.url, {
            headers: { Authorization: `Bearer ${this.opts.token}` },
            handshakeTimeout: HANDSHAKE_TIMEOUT_MS,
        });

        this.ws.on('open', () => this.onOpen());
        this.ws.on('message', (raw) => this.onMessage(raw));
        this.ws.on('pong', () => this.onPong());
        this.ws.on('close', () => this.scheduleReconnect());
        this.ws.on('error', (err) => this.opts.onError?.(err as Error));
    }

    /**
     * Close the connection and stop reconnect attempts.
     *
     * Sets `closed = true` so any in-flight scheduleReconnect() short-circuits.
     * Clears both timers and terminates the underlying socket immediately
     * (no graceful close handshake — we are tearing down).
     */
    close(): void {
        this.closed = true;
        this.clearTimers();
        this.ws?.terminate();
        this.ws = null;
    }

    /** Last server-side resume cursor — useful for tests + diagnostics. */
    getLastSeenId(): string | null {
        return this.lastSeenId;
    }

    // ── private ────────────────────────────────────────────────────────────────

    private onOpen(): void {
        this.attempt = 0;
        // Pitfall 3: only include last_seen_id when we have one. A fresh booking
        // sends bare {actions:[...]} to avoid replaying hours of stale logs.
        const subscribe = this.lastSeenId
            ? { last_seen_id: this.lastSeenId, actions: [...SUBSCRIBE_ACTIONS] }
            : { actions: [...SUBSCRIBE_ACTIONS] };
        this.ws!.send(JSON.stringify(subscribe));
        this.startHeartbeat();
    }

    private onMessage(raw: unknown): void {
        try {
            // ws emits Buffer | ArrayBuffer | Buffer[] for binary frames — for our
            // text-only protocol the .toString() coercion always yields the JSON.
            const text = Buffer.isBuffer(raw)
                ? raw.toString('utf8')
                : Array.isArray(raw)
                  ? Buffer.concat(raw as Buffer[]).toString('utf8')
                  : String(raw);
            const parsed = JSON.parse(text);
            const frame = LogFrameSchema.safeParse(parsed);

            if (!frame.success) {
                // T-08-10-02 mitigation: log + surface via onError; never crash.
                this.opts.onError?.(
                    new Error(`CrconClient: malformed frame — ${frame.error.message}`),
                );
                return;
            }

            if (frame.data.error) {
                this.opts.onError?.(new Error(`CrconClient: server error — ${frame.data.error}`));
            }

            this.opts.onLogs(frame.data.logs, frame.data.last_seen_id ?? null);
            if (frame.data.last_seen_id) {
                this.lastSeenId = frame.data.last_seen_id;
            }
        } catch (e) {
            this.opts.onError?.(e as Error);
        }
    }

    private onPong(): void {
        this.alive = true;
    }

    private scheduleReconnect(): void {
        this.clearHeartbeat();
        if (this.closed) return;

        // Exponential backoff + 0-1000ms jitter, capped at MAX_BACKOFF_MS.
        // attempt=0 → 1s+jitter; attempt=1 → 2s+jitter; … attempt≥5 → 30s+jitter.
        const base = Math.min(MAX_BACKOFF_MS, 1_000 * 2 ** this.attempt);
        const jitter = Math.random() * 1_000;
        const delay = base + jitter;
        this.attempt++;

        this.reconnectTimer = setTimeout(() => {
            this.reconnectTimer = undefined;
            this.connect();
        }, delay);
    }

    private startHeartbeat(): void {
        this.clearHeartbeat();
        this.alive = true;
        const interval = this.opts.heartbeatIntervalMs ?? HEARTBEAT_INTERVAL_MS;
        this.heartbeatTimer = setInterval(() => {
            if (!this.alive) {
                // No pong received in the previous 30s window — terminate. The
                // natural close handler will then call scheduleReconnect.
                this.ws?.terminate();
                return;
            }
            this.alive = false;
            this.ws?.ping();
        }, interval);
    }

    private clearHeartbeat(): void {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = undefined;
        }
    }

    private clearTimers(): void {
        this.clearHeartbeat();
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = undefined;
        }
    }
}
