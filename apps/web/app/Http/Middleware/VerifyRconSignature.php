<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Hmac\HmacVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the (X-Rcon-Timestamp, X-Rcon-Nonce, X-Rcon-Signature) triple on every
 * worker→web internal POST and gates the request through three sequential checks:
 *
 *   1. **Header presence** — all three headers MUST be set (401 'missing rcon auth headers').
 *   2. **Freshness window** — `abs(now_ms - timestamp_ms) <= rcon.freshness_window_ms`
 *      (default 60_000 ms). Both directions covered — clock skew can pull either way
 *      (Pitfall 2). 401 'stale signature' on miss.
 *   3. **HMAC verification** — `HmacVerifier::verify()` over the raw request body
 *      (`$request->getContent()` — DO NOT call `$request->json()` here; Pitfall 1).
 *      Constant-time compare via `hash_equals` inside the verifier. 401 'bad signature'.
 *   4. **Nonce single-use** — `Redis::set('rcon:nonce:<uuid>', '1', 'EX', 120, 'NX')`.
 *      Returns `null|false` when the key already exists, indicating a replay within
 *      the 120s window (2× the freshness window per RESEARCH). 401 'replayed nonce'.
 *
 * Source: 08-RESEARCH.md "HMAC Architecture" lines 385-417 (canonical shape) +
 * CON-arch-rcon-to-web-comm (the contract this middleware enforces).
 *
 * IMPORTANT — secrecy hygiene (Pitfall 9 / T-08-05-04):
 *  - This middleware NEVER logs `$sig`, the expected signature, or the secret.
 *  - 401 bodies use plain English labels (no diff, no hex, no length).
 *  - Distinct messages per failure mode are intentional for ops debuggability —
 *    they reveal which check failed but not the secret material that would let
 *    an attacker iterate.
 *
 * Used by:
 *  - Plan 08-06 (internal RCON ingest routes — mounted via `->middleware('rcon.signature')`).
 *  - Plan 08-13 phase verification (cross-tier signature contract probe).
 */
final class VerifyRconSignature
{
    public function __construct(
        private readonly HmacVerifier $verifier,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->header('X-Rcon-Timestamp');
        $nonce = $request->header('X-Rcon-Nonce');
        $sig = $request->header('X-Rcon-Signature');
        $body = $request->getContent(); // raw bytes — DO NOT call $request->json() (Pitfall 1)

        if (! is_string($timestamp) || $timestamp === ''
            || ! is_string($nonce) || $nonce === ''
            || ! is_string($sig) || $sig === ''
        ) {
            abort(401, 'missing rcon auth headers');
        }

        $freshnessWindowMs = (int) config('rcon.freshness_window_ms', 60_000);
        // Use Carbon::now() so Carbon::setTestNow() in tests pins the clock.
        // `microtime(true) * 1000` would bypass the test-now binding and break
        // deterministic timestamp assertions in the Pest suite.
        $nowMs = (int) (Carbon::now()->getTimestamp() * 1000) + (int) (Carbon::now()->milli);
        $age = abs($nowMs - (int) $timestamp);
        if ($age > $freshnessWindowMs) {
            abort(401, 'stale signature');
        }

        $secret = (string) config('rcon.hmac_secret', '');
        if (! $this->verifier->verify($timestamp, $body, $sig, $secret)) {
            abort(401, 'bad signature');
        }

        $nonceTtl = (int) config('rcon.nonce_ttl_seconds', 120);
        // Canonical Laravel idiom for atomic SET-if-not-exists with TTL — the
        // variadic 5-arg form maps to `SET key val EX <ttl> NX` on the wire.
        // PHPStan's phpredis stubs (`@mixin \Redis`) declare a stricter 3-arg
        // shape; the real Illuminate\Redis\Connections\PhpRedisConnection::set
        // (vendor/.../PhpRedisConnection.php:82) accepts the variadic form
        // and is what's called at runtime. Returns bool(true) on set, bool(false)
        // on replay (second SET ... NX inside the 120s TTL window).
        // @phpstan-ignore-next-line argument.type,arguments.count
        $stored = Redis::set("rcon:nonce:{$nonce}", '1', 'EX', $nonceTtl, 'NX');
        if ($stored !== true) {
            abort(401, 'replayed nonce');
        }

        return $next($request);
    }
}
