<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\MatchServer;
use Illuminate\Http\JsonResponse;

/**
 * Source: .planning/phases/08-rcon-automation/08-06-PLAN.md task 1.
 *
 * GET /api/internal/match-servers/{server}/credentials — invoked by the rcon-worker
 * CrconClient (plan 08-10) on session open to retrieve the decrypted CRCON bearer
 * token + host + port for the target server.
 *
 * Auth: rcon.signature middleware (plan 08-05) is the trust principal. HMAC over
 * (timestamp + raw body) — for GET the body is empty so the signature degenerates
 * to HMAC(timestamp + "") which is still safe (timestamp is unique-per-request).
 *
 * Wire contract (must_haves.truths #3):
 *   200 + { host: string, port_rcon: int, api_token: string }.
 *   404 if server unknown OR is_active=false (scope chain `active()->findOrFail`).
 *   401 from middleware on signature failure.
 *
 * **Secrecy hygiene** (T-08-06-02 — api_token leak via cached/logged response):
 *  - Response uses default `application/json` Content-Type with no `Cache-Control`
 *    override — Laravel's default for /api is `private, no-store, no-cache, must-revalidate`
 *    via the StartSession middleware skip on the api stack.
 *  - We NEVER log the api_token. Plan 08-12 extends the Laravel log redact list to
 *    mask `api_token` paths in all log channels (defence in depth for accidental
 *    `Log::info($response)` calls in downstream plans).
 *  - The Eloquent `encrypted:array` cast on MatchServer::$credentials_encrypted
 *    decrypts at access time — the plaintext only exists in this controller's
 *    response body for the duration of the worker's session-open call.
 *
 * Threat refs:
 *  - T-08-03-01 (credential leak at rest): mitigated by encrypted:array cast (8-03).
 *  - T-08-06-02 (credential leak in transit/logs): mitigated by HMAC channel +
 *    log redaction (8-12) + no-cache default.
 */
final class MatchServerCredentialsController extends Controller
{
    public function show(string $server): JsonResponse
    {
        /** @var MatchServer $matchServer */
        $matchServer = MatchServer::query()
            ->active()
            ->where('id', $server)
            ->firstOrFail();

        /** @var array{api_token?: string} $credentials */
        $credentials = $matchServer->credentials_encrypted ?? [];

        return response()->json([
            'host' => $matchServer->host,
            'port_rcon' => $matchServer->port_rcon,
            'api_token' => $credentials['api_token'] ?? '',
        ]);
    }
}
