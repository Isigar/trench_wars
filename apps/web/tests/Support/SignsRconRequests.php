<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Hmac\HmacVerifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Source: .planning/phases/08-rcon-automation/08-06-PLAN.md task 2 SignsRconRequests block.
 *
 * Reusable Pest trait for plans 08-06..08-12 test suites that exercise the
 * HMAC-protected /api/internal/* endpoints. Pre-converts the four request
 * headers (X-Rcon-Timestamp, X-Rcon-Nonce, X-Rcon-Signature, Content-Type) to
 * the Symfony `HTTP_*` server-var convention so they survive the `$this->call()`
 * path (Laravel's `withHeaders()` is skipped by `call()` — only the higher-level
 * `post()`/`json()` helpers convert; see 08-05 SUMMARY tech-stack pattern #1).
 *
 * Why a separate helper instead of the per-test `rconServerVars()` function in
 * VerifyRconSignatureTest? That function pinned a const `RCON_TEST_SECRET` and
 * lived in test scope. As soon as a second test suite touches /api/internal/*
 * routes (plans 08-07, 08-08, 08-12), we need a reusable surface that:
 *   1. Reads the secret from `config('rcon.hmac_secret')` — keeps tests in lock-step
 *      with the production secret-resolution chain.
 *   2. Exposes both signedJsonPost() and signedGet() ergonomics so GETs don't have
 *      to fake an empty-body POST.
 *   3. Pins Carbon::now() implicitly when the caller hasn't already setTestNow'd —
 *      tests that don't care about the exact timestamp shouldn't have to assert it.
 *
 * Pest usage (in a test file):
 *   uses(\Tests\Support\SignsRconRequests::class);
 *
 *   it('does the thing', function (): void {
 *       $response = $this->signedJsonPost('/api/internal/match/.../events', [
 *           'events' => [/* ... * /],
 *       ]);
 *       expect($response->getStatusCode())->toBe(202);
 *   });
 */
trait SignsRconRequests
{
    /**
     * Build the Symfony server-var array for a signed RCON request.
     *
     * The body is signed VERBATIM — callers MUST pass the same bytes to
     * `$this->call()` as the 7th argument. Encoding/decoding mismatches between
     * `$body` here and the bytes Symfony sees would break the HMAC equality check.
     *
     * @return array<string, string>
     */
    protected function rconServerVars(string $body): array
    {
        $secret = (string) config('rcon.hmac_secret', '');
        $timestampMs = (string) (int) (Carbon::now()->getTimestamp() * 1000 + (int) Carbon::now()->milli);
        $nonce = (string) Str::uuid();

        $sig = (new HmacVerifier)->sign($timestampMs, $body, $secret);

        return [
            'HTTP_X_RCON_TIMESTAMP' => $timestampMs,
            'HTTP_X_RCON_NONCE' => $nonce,
            'HTTP_X_RCON_SIGNATURE' => $sig,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];
    }

    /**
     * POST `$payload` to `$url` with a fresh HMAC signature over the JSON-encoded body.
     *
     * Body is `json_encode($payload, JSON_UNESCAPED_SLASHES)` — fixed flag set so the
     * raw bytes the helper signs are deterministic regardless of caller's locale or
     * the test's `Json` macro. JSON_UNESCAPED_SLASHES mirrors the Node worker's
     * default `JSON.stringify` output (no backslash before forward slashes in URLs).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function signedJsonPost(string $url, array $payload): TestResponse
    {
        $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $server = $this->rconServerVars($body);

        return $this->call('POST', $url, [], [], [], $server, $body);
    }

    /**
     * GET `$url` with a fresh HMAC signature. Body is the empty string (GETs carry no
     * payload); the signature is HMAC(timestamp + "") which is still safe because the
     * timestamp + nonce are unique-per-request and gate replays.
     */
    protected function signedGet(string $url): TestResponse
    {
        $server = $this->rconServerVars('');

        return $this->call('GET', $url, [], [], [], $server);
    }
}
