// Plan 08-10 task 1 — zod schemas for the CRCON `/ws/logs` wire frames.
// Source: .planning/phases/08-rcon-automation/08-10-PLAN.md must_haves.artifacts (types.ts).
// Source (wire shape): 08-RESEARCH.md § CRCON API → Log Action Types (AllLogTypes enum)
//                     + Log Frame Schema (id / log / timestamp_ms / action).
//
// CrconClient validates inbound frames against `LogFrameSchema` before passing to
// the onLogs callback. zod parse failures are surfaced via onError and the
// connection is NOT dropped (graceful degradation — Pattern 1 reconnect handles
// the rare case of CRCON emitting a malformed frame mid-stream).

import { z } from 'zod';

/**
 * Single CRCON log entry as carried inside a LogFrame.logs[] array.
 *
 * `log.action` is the discriminator key — see CrconEventNormaliser.normalise()
 * for the 7-action switch. `log.timestamp_ms` is the CRCON-side wall clock at
 * the moment of the event (we round-trip it to `occurred_at` ISO string).
 */
export const LogEntrySchema = z.object({
    id: z.string(),
    log: z
        .object({
            action: z.string(),
            timestamp_ms: z.number().int().nonnegative().optional(),
        })
        .passthrough(), // keep unknown keys (steam_id_64_1, player, weapon, …) intact for the normaliser
});

/**
 * Wire frame received from CRCON over `/ws/logs`. May contain zero or more entries.
 *
 * `last_seen_id` is the resume cursor — CrconClient persists it across reconnects
 * and replays it via the subscribe message (Pattern 1 + Pitfall 3 deferral).
 *
 * `error` is the optional server-side error field — when present, we surface it
 * via onError without dropping the connection (CRCON sometimes emits sub-frame
 * warnings that are recoverable).
 */
export const LogFrameSchema = z.object({
    last_seen_id: z.string().nullable().optional(),
    logs: z.array(LogEntrySchema).default([]),
    error: z.string().nullable().optional(),
});

export type LogEntry = z.infer<typeof LogEntrySchema>;
export type LogFrame = z.infer<typeof LogFrameSchema>;
