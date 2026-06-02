// Wave 0 skeleton — Phase 8 plan 08-01 task 1.
// Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 1 behaviour list.
// Source (env shape): 08-RESEARCH.md § HMAC Architecture / § Worker Architecture.
//
// loadConfig() returns a zod-validated env shape. WEB_HMAC_SECRET min length is 32
// (HMAC-SHA256 secret entropy floor). undici fetch is the outbound transport — see
// HmacSigner.ts for the signing contract. Real worker boot wiring lands in plan 08-11.
import { z } from 'zod';

/**
 * Parse an env string into a boolean. `z.coerce.boolean()` treats any non-empty
 * string (including "false") as true, which is a footgun for env toggles — so we
 * map explicit truthy/falsy spellings instead. `undefined` is left untouched so a
 * caller-supplied `.default()` can apply.
 */
const envBoolean = z
    .preprocess((v) => {
        if (typeof v !== 'string') return v;
        const s = v.trim().toLowerCase();
        if (['1', 'true', 'yes', 'on'].includes(s)) return true;
        if (['0', 'false', 'no', 'off', ''].includes(s)) return false;
        return v;
    }, z.boolean());

const ConfigSchema = z.object({
    WEB_HMAC_SECRET: z.string().min(32),
    WEB_INTERNAL_URL: z.string().url(),
    NODE_ENV: z.enum(['development', 'test', 'production']).default('development'),
    POLL_INTERVAL_MS: z.coerce.number().int().positive().default(30_000),
    REDIS_URL: z.string().url().optional(),
    /**
     * Transport security for the CRCON `/ws/logs` connection. When true the
     * worker dials `wss://` (TLS); when false it falls back to plaintext `ws://`.
     * The CRCON credentials wire contract carries only a bare host (no scheme),
     * so we cannot derive the scheme from the URL — this toggle is the source of
     * truth. Defaults to SECURE so production never sends the RCON bearer token
     * in cleartext by omission. Loopback hosts (localhost / 127.0.0.1 / ::1)
     * always use plaintext `ws://` regardless of this flag, so local dev and the
     * integration test harness keep working without setting anything.
     */
    CRCON_WS_SECURE: envBoolean.optional().default(true),
});

export type Config = z.infer<typeof ConfigSchema>;

/**
 * Validate process.env into the worker's typed Config shape.
 *
 * Throws zod's flattened error on missing/invalid env. Plan 08-11 wires this into
 * the boot path; this Wave 0 export is contract-only.
 */
export function loadConfig(env: NodeJS.ProcessEnv = process.env): Config {
    return ConfigSchema.parse(env);
}
