// Trenchwars bot — outbound polling worker.
//
// Source: .planning/phases/05-discord-bot-v1/05-11-PLAN.md task 1 (Wave 10).
// RESEARCH §Pattern 4 verbatim. Every env.OUTBOUND_POLL_INTERVAL_MS (default
// 5s) the worker:
//
//   1. GET /api/bot/outbound-messages?status=pending&limit=20
//      (the web side atomically claims them via plan 05-04's lockForUpdate
//      transaction so a parallel poll would observe a disjoint set even if
//      we ever ran multiple bot replicas.)
//   2. For each row, call render(client, row) — for match_announce that
//      builds the matchCard + posts/edits a Discord message; for role_sync
//      that does a guild member roles.add/remove via the discord.js REST
//      manager (Pattern 2 automatic 429 retry).
//   3. POST /outbound-messages/{id}/sent OR /failed depending on the render
//      outcome.
//
// Safety properties enforced here (per `<threat_model>`):
//
//   T-05-11-01 — overlap-skip guard. If a previous tick is still running
//                (`running === true`), the next setInterval fire is dropped.
//                This prevents a hung tick from piling up N concurrent
//                polls; the operator sees stalled outbound in Filament
//                (plan 05-07) and restarts the bot.
//   T-05-11-02 — per-row try/catch around render + markFailed; nested
//                catch around markFailed itself in case the web API is
//                down. One bad row never breaks the loop.
//   T-05-11-03 — err.message that bubbles up from api.ts has already been
//                scrubbed of WEB_API_TOKEN at the source (plan 05-08
//                api.ts). The slice(0, 2000) cap matches the
//                BotApiOutboundController's last_error column shape.
//
// Testability: the tick body is extracted into the public
// `processOutboundTick(client)` helper so unit tests can invoke a single
// tick directly without dealing with timers. The setInterval wrapper +
// overlap-skip flag are tested separately via vi.useFakeTimers.

import type { Client } from 'discord.js';

import { env } from '../env.js';
import type { OutboundRow } from '../types/apiContracts.js';
import { api } from './api.js';
import { render } from './render.js';

let running = false;

// Handle for the interval started by startOutboundWorker. Captured at module
// scope so the entrypoint's graceful-shutdown handler can stop the poll loop
// via stopOutboundWorker() without threading the handle through ready.ts.
let pollHandle: NodeJS.Timeout | null = null;

/**
 * processOutboundTick — single poll-and-dispatch pass.
 *
 * Public for unit testing. The setInterval wrapper in startOutboundWorker
 * adds the overlap-skip guard + top-level try/catch around this function.
 */
export async function processOutboundTick(client: Client): Promise<void> {
    // Web wraps every BotApiOutboundController response in a { data: ... } envelope.
    const { data: rows } = await api.get<{ data: OutboundRow[] }>(
        '/outbound-messages?status=pending&limit=20',
    );
    for (const row of rows) {
        try {
            const result = await render(client, row);
            await api.post(`/outbound-messages/${row.id}/sent`, {
                sent_message_id: result.discordMessageId,
            });
        } catch (err) {
            const message = err instanceof Error ? err.message : String(err);
            try {
                await api.post(`/outbound-messages/${row.id}/failed`, {
                    last_error: message.slice(0, 2000),
                });
            } catch (ackErr) {
                console.error('[bot/outbound] markFailed failed:', ackErr);
            }
        }
    }
}

/**
 * startOutboundWorker — Pattern 4 verbatim setInterval-driven poll loop.
 *
 * Returns the NodeJS.Timeout handle so a caller (or test) can clearInterval
 * for clean shutdown. Module-level `running` flag drops overlapping ticks
 * if one tick is still in flight when the next interval fires.
 */
export function startOutboundWorker(
    client: Client,
    intervalMs: number = env.OUTBOUND_POLL_INTERVAL_MS,
): NodeJS.Timeout {
    console.log(`[bot/outbound] Starting poll loop every ${intervalMs}ms`);
    const handle = setInterval(() => {
        if (running) {
            return;
        }
        running = true;
        processOutboundTick(client)
            .catch((err: unknown) => {
                console.error('[bot/outbound] poll tick error:', err);
            })
            .finally(() => {
                running = false;
            });
    }, intervalMs);
    pollHandle = handle;
    return handle;
}

/**
 * stopOutboundWorker — clear the poll loop interval for clean shutdown.
 *
 * Called by the entrypoint's SIGTERM/SIGINT handler (index.ts) so the process
 * can exit without the setInterval keeping the event loop alive. Safe to call
 * when no worker is running (the null guard makes it a no-op).
 */
export function stopOutboundWorker(): void {
    if (pollHandle !== null) {
        clearInterval(pollHandle);
        pollHandle = null;
    }
}

/**
 * __resetRunningFlagForTests — test-only helper.
 *
 * The module-level `running` flag persists across test cases within the
 * same test file. Tests that exercise the overlap-skip guard call this
 * after their assertion to leave the flag in a known state for the next
 * test. Not exported from index.ts (this is a service module, no barrel).
 */
export function __resetRunningFlagForTests(): void {
    running = false;
}
