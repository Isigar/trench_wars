<?php

declare(strict_types=1);

/*
| GREEN — plan 08-05 task 2.
| Replaces the Wave 0 RED stub authored in 08-01.
|
| Verifies the (X-Rcon-Timestamp, X-Rcon-Nonce, X-Rcon-Signature) gate end to
| end via a temporary in-test route mounted behind the `rcon.signature` middleware
| alias registered in bootstrap/app.php (plan 08-05 task 1).
|
| 8 cases covering every branch of CON-arch-rcon-to-web-comm:
|   1. happy path — fresh signed request → 204
|   2. missing X-Rcon-Timestamp → 401 "missing"
|   3. missing X-Rcon-Signature → 401 "missing"
|   4. stale timestamp (>60s past) → 401 "stale"
|   5. stale timestamp (>60s future) → 401 "stale" (Pitfall 2 — abs both ways)
|   6. wrong secret → 401 "bad signature"
|   7. tampered body → 401 "bad signature"
|   8. replayed nonce → 1st request 204; 2nd with same nonce → 401 "replayed"
|
| Each case constructs the HMAC manually (does NOT call worker code) so the
| middleware is verified in isolation. Carbon::setTestNow pins deterministic
| timestamps. Redis::flushdb() in beforeEach resets nonce state per test.
| actingAs() is intentionally NOT used — this trust principal is separate from
| Sanctum (RESEARCH "Alternatives Considered: Two-channel auth").
|
| IMPORTANT — the test uses `$this->call(method, uri, [], [], [], $server, $body)`
| with headers pre-converted to `HTTP_*` server vars instead of `withHeaders()`.
| Laravel's `withHeaders()` stores headers in `$defaultHeaders` but `call()` does
| NOT merge them into the Symfony request — only `post()`, `json()`, etc. apply
| the conversion. Since this gate signs the RAW body, we must build the request
| at the `call()` level and pre-transform headers via Symfony's `HTTP_*` convention.
*/

use App\Support\Hmac\HmacVerifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

const RCON_TEST_SECRET = 'test-hmac-secret-for-phase-8-signature-gate';
const RCON_TEST_ROUTE = '/__test/rcon-echo';

/**
 * Build the four-header set (signature, timestamp, nonce, content-type) pre-converted
 * to `HTTP_*` server vars that Symfony's Request::createFromGlobals reads.
 *
 * @return array<string, string|int>
 */
function rconServerVars(string $timestampMs, string $body, string $secret = RCON_TEST_SECRET, ?string $nonce = null): array
{
    $verifier = new HmacVerifier;
    $nonce ??= Str::uuid()->toString();
    $sig = $verifier->sign($timestampMs, $body, $secret);

    return [
        'HTTP_X_RCON_TIMESTAMP' => $timestampMs,
        'HTTP_X_RCON_NONCE' => $nonce,
        'HTTP_X_RCON_SIGNATURE' => $sig,
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ];
}

beforeEach(function (): void {
    // Pin the symmetric secret for the test scope (phpunit.xml does not set
    // WEB_HMAC_SECRET — the production secret lives in Railway env-groups).
    config(['rcon.hmac_secret' => RCON_TEST_SECRET]);

    // Reset nonce state between tests so test 8's replay assertion is not
    // poisoned by a nonce left over from an earlier happy-path case.
    Redis::flushdb();

    // Temporary protected route — the middleware alias was registered in
    // plan 08-05 task 1 (bootstrap/app.php). The route only exists for the
    // duration of this test and is NEVER reachable in production.
    Route::post(RCON_TEST_ROUTE, fn () => response()->noContent())
        ->middleware('rcon.signature');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('happy path — fresh signed request returns 204', function (): void {
    $now = Carbon::create(2026, 5, 14, 12, 0, 0);
    Carbon::setTestNow($now);
    $timestampMs = (string) (int) ($now->getTimestamp() * 1000);
    $body = '{"event":"game_start","map":"Foy"}';

    $response = $this->call('POST', RCON_TEST_ROUTE, [], [], [], rconServerVars($timestampMs, $body), $body);

    expect($response->getStatusCode())->toBe(204);
});

it('rejects requests missing X-Rcon-Timestamp with 401 "missing"', function (): void {
    $now = Carbon::create(2026, 5, 14, 12, 0, 0);
    Carbon::setTestNow($now);
    $body = '{}';
    $server = rconServerVars((string) (int) ($now->getTimestamp() * 1000), $body);
    unset($server['HTTP_X_RCON_TIMESTAMP']);

    $response = $this->call('POST', RCON_TEST_ROUTE, [], [], [], $server, $body);

    expect($response->getStatusCode())->toBe(401);
    expect($response->exception?->getMessage())->toContain('missing');
});

it('rejects requests missing X-Rcon-Signature with 401 "missing"', function (): void {
    $now = Carbon::create(2026, 5, 14, 12, 0, 0);
    Carbon::setTestNow($now);
    $body = '{}';
    $server = rconServerVars((string) (int) ($now->getTimestamp() * 1000), $body);
    unset($server['HTTP_X_RCON_SIGNATURE']);

    $response = $this->call('POST', RCON_TEST_ROUTE, [], [], [], $server, $body);

    expect($response->getStatusCode())->toBe(401);
    expect($response->exception?->getMessage())->toContain('missing');
});

it('rejects stale timestamps >60s in the past with 401 "stale"', function (): void {
    $now = Carbon::create(2026, 5, 14, 12, 0, 0);
    Carbon::setTestNow($now);
    // Timestamp 61s ago — outside the 60_000 ms freshness window.
    $staleMs = (string) (int) (($now->getTimestamp() - 61) * 1000);
    $body = '{}';

    $response = $this->call('POST', RCON_TEST_ROUTE, [], [], [], rconServerVars($staleMs, $body), $body);

    expect($response->getStatusCode())->toBe(401);
    expect($response->exception?->getMessage())->toContain('stale');
});

it('rejects stale timestamps >60s in the future with 401 "stale" (Pitfall 2)', function (): void {
    $now = Carbon::create(2026, 5, 14, 12, 0, 0);
    Carbon::setTestNow($now);
    // Timestamp 61s in the future — clock skew the OTHER way. The middleware
    // uses abs() so both directions are gated (Pitfall 2 — raw subtraction
    // would silently accept future timestamps).
    $futureMs = (string) (int) (($now->getTimestamp() + 61) * 1000);
    $body = '{}';

    $response = $this->call('POST', RCON_TEST_ROUTE, [], [], [], rconServerVars($futureMs, $body), $body);

    expect($response->getStatusCode())->toBe(401);
    expect($response->exception?->getMessage())->toContain('stale');
});

it('rejects requests signed with the wrong secret with 401 "bad signature"', function (): void {
    $now = Carbon::create(2026, 5, 14, 12, 0, 0);
    Carbon::setTestNow($now);
    $timestampMs = (string) (int) ($now->getTimestamp() * 1000);
    $body = '{"event":"game_start"}';

    // Headers signed with the WRONG secret; the middleware verifies against
    // config('rcon.hmac_secret') === RCON_TEST_SECRET so this must fail.
    $server = rconServerVars($timestampMs, $body, 'wrong-secret-xxx');

    $response = $this->call('POST', RCON_TEST_ROUTE, [], [], [], $server, $body);

    expect($response->getStatusCode())->toBe(401);
    expect($response->exception?->getMessage())->toContain('bad signature');
});

it('rejects requests where the body was tampered post-signing with 401 "bad signature"', function (): void {
    $now = Carbon::create(2026, 5, 14, 12, 0, 0);
    Carbon::setTestNow($now);
    $timestampMs = (string) (int) ($now->getTimestamp() * 1000);

    // Sign body A …
    $signedBody = '{"event":"game_start","map":"Foy"}';
    $server = rconServerVars($timestampMs, $signedBody);

    // … but POST body B (single character flip).
    $tamperedBody = '{"event":"game_start","map":"Hill"}';

    $response = $this->call('POST', RCON_TEST_ROUTE, [], [], [], $server, $tamperedBody);

    expect($response->getStatusCode())->toBe(401);
    expect($response->exception?->getMessage())->toContain('bad signature');
});

it('rejects replayed nonces within the 120s TTL window with 401 "replayed"', function (): void {
    $now = Carbon::create(2026, 5, 14, 12, 0, 0);
    Carbon::setTestNow($now);
    $timestampMs = (string) (int) ($now->getTimestamp() * 1000);
    $body = '{"event":"round_start","round_number":1}';

    // SAME nonce, used twice. First request lands at 204, second is rejected
    // by the Redis SETNX EX 120 NX gate even though the timestamp is still
    // within the freshness window.
    $sharedNonce = Str::uuid()->toString();
    $server = rconServerVars($timestampMs, $body, RCON_TEST_SECRET, $sharedNonce);

    $first = $this->call('POST', RCON_TEST_ROUTE, [], [], [], $server, $body);
    expect($first->getStatusCode())->toBe(204);

    $second = $this->call('POST', RCON_TEST_ROUTE, [], [], [], $server, $body);

    expect($second->getStatusCode())->toBe(401);
    expect($second->exception?->getMessage())->toContain('replayed');
});
