// Wave 0 skeleton — Phase 8 plan 08-01 task 1.
// Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 1 behaviour list.
// Source (env shape): 08-RESEARCH.md § HMAC Architecture / § Worker Architecture.
//
// loadConfig() returns a zod-validated env shape. WEB_HMAC_SECRET min length is 32
// (HMAC-SHA256 secret entropy floor). undici fetch is the outbound transport — see
// HmacSigner.ts for the signing contract. Real worker boot wiring lands in plan 08-11.
import { z } from 'zod';

const ConfigSchema = z.object({
    WEB_HMAC_SECRET: z.string().min(32),
    WEB_INTERNAL_URL: z.string().url(),
    NODE_ENV: z.enum(['development', 'test', 'production']).default('development'),
    POLL_INTERVAL_MS: z.coerce.number().int().positive().default(30_000),
    REDIS_URL: z.string().url().optional(),
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
