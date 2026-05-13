// Trenchwars bot — fail-fast environment variable validation.
//
// Source: .planning/phases/05-discord-bot-v1/05-08-PLAN.md task 1 (Wave 6).
// Pattern matches the rcon-worker analog and the RESEARCH §Pitfall 3 / T-05-08-02
// mitigation: every required env var MUST throw at module-load time if missing
// or empty. There is no fallback to a development default — production safety
// requires loud failure when WEB_API_TOKEN is unset rather than silently making
// all API calls without authentication.
//
// The exported `env` constant is frozen (`as const`) at module load; subsequent
// imports see the same object reference and the same values.

function required(key: string): string {
    const v = process.env[key];
    if (v === undefined || v === '') {
        throw new Error(`[bot/env] Missing required env var: ${key}`);
    }
    return v;
}

function optional(key: string, fallback: string): string {
    const v = process.env[key];
    return v === undefined || v === '' ? fallback : v;
}

export const env = {
    DISCORD_BOT_TOKEN: required('DISCORD_BOT_TOKEN'),
    DISCORD_APPLICATION_ID: required('DISCORD_APPLICATION_ID'),
    DISCORD_GUILD_ID: required('DISCORD_GUILD_ID'),
    WEB_API_URL: required('WEB_API_URL'),
    WEB_API_TOKEN: required('WEB_API_TOKEN'),
    OUTBOUND_POLL_INTERVAL_MS: Number.parseInt(optional('OUTBOUND_POLL_INTERVAL_MS', '5000'), 10),
} as const;
