// Wave 0 skeleton — Phase 8 plan 08-01 task 1.
// Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 1 behaviour list.
// Source (redact list): 08-RESEARCH.md § Common Pitfalls — Pitfall 9 (PII in logs).
//
// Pino instance configured with a redact list baked in at construction time so that
// the first commit of plan 08-10 (CRCON wire client) cannot accidentally emit raw
// steam IDs or player names. Phase 8 mitigates threat T-08-01-02 (information
// disclosure via worker logs) — see 08-01-PLAN.md threat register.
import pino from 'pino';

export const logger = pino({
    level: process.env.LOG_LEVEL ?? 'info',
    redact: {
        paths: ['steam_id_64', 'player', 'victim', 'killer', '*.steam_id_64', '*.player', '*.victim', '*.killer'],
        censor: '[REDACTED]',
    },
});

export type Logger = typeof logger;
