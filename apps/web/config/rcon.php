<?php

declare(strict_types=1);

/*
| Source: .planning/phases/08-rcon-automation/08-05-PLAN.md task 1 + RESEARCH
| "HMAC Architecture" + Open Question 1 (CRCON v10+ pin RESOLVED).
|
| Runtime configuration for the worker↔web internal channel. The HMAC gate
| (App\Http\Middleware\VerifyRconSignature) reads these keys on every request;
| the worker (apps/rcon-worker/, plan 08-10) reads `hmac_secret` to sign its
| outgoing POSTs. Both tiers MUST resolve `WEB_HMAC_SECRET` to the same value
| or the constant-time HMAC compare will always reject.
|
| Threat refs (08-05 register):
|  - T-08-05-04 (secret leak via log): hmac_secret is sourced from env only;
|    .env.example commits an empty string and Railway env-groups inject the
|    runtime value (D-014). The middleware never logs the value (Pitfall 9).
|  - T-08-05-06 (empty secret deployed accidentally): HmacVerifier::sign throws
|    InvalidArgumentException when the resolved secret is the empty string;
|    fail-loud rather than fail-open.
*/

return [
    /*
    |--------------------------------------------------------------------------
    | HMAC secret (worker↔web shared symmetric key)
    |--------------------------------------------------------------------------
    | The worker signs each request with `HMAC-SHA256(timestamp + raw_body)`
    | keyed on this secret; the web middleware re-derives the digest and
    | constant-time-compares. Both tiers MUST resolve the SAME value.
    |
    | Production: injected via Railway env-group (D-014).
    | Local dev: set in apps/web/.env.
    | Tests: phpunit.xml overrides via <env name="WEB_HMAC_SECRET" .../>.
    */
    'hmac_secret' => env('WEB_HMAC_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Freshness window (milliseconds)
    |--------------------------------------------------------------------------
    | `abs(now_ms - timestamp_ms)` must be <= this value or the middleware
    | returns 401 'stale signature'. abs() (not raw diff) covers clock skew
    | in BOTH directions — Pitfall 2.
    |
    | 60_000 ms (60s) per CON-arch-rcon-to-web-comm. Mirrors the AWS SigV4
    | norm and gives plenty of headroom for NTP drift on Railway nodes.
    */
    'freshness_window_ms' => env('RCON_FRESHNESS_WINDOW_MS', 60_000),

    /*
    |--------------------------------------------------------------------------
    | Nonce TTL (seconds)
    |--------------------------------------------------------------------------
    | `Redis::set('rcon:nonce:<uuid>', '1', 'EX', this, 'NX')` — the second
    | request bearing the same nonce within this window is rejected as a
    | replay (T-08-05-01). 2× the freshness window per RESEARCH — gives the
    | freshness check time to expire the timestamp before Redis forgets the
    | nonce (defence in depth — both gates must be on for a replay to land).
    */
    'nonce_ttl_seconds' => env('RCON_NONCE_TTL_SECONDS', 120),

    /*
    |--------------------------------------------------------------------------
    | CRCON server version pin (Open Question 1 RESOLVED)
    |--------------------------------------------------------------------------
    | The minimum CRCON server version the worker is built against. The
    | worker's RCON wire-format expectations (auth handshake bytes, stream
    | id shape) are pinned to v10.0.0+; the league deploy bundles CRCON
    | alongside the game servers (D-005), so this pin is OPERATIONAL — it
    | tells the worker which CRCON tag to deploy, not which to negotiate at
    | runtime.
    */
    'crcon_version_pin' => env('CRCON_VERSION_PIN', '10.0.0'),
];
