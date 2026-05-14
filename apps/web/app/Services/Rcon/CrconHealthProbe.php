<?php

declare(strict_types=1);

namespace App\Services\Rcon;

use App\Models\MatchServer;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Source: .planning/phases/08-rcon-automation/08-09-PLAN.md task 2 +
 *         <interfaces> CrconHealthProbe block.
 *
 * Stateless health probe. Calls CRCON's `/api/get_map_rotation` endpoint
 * (RESEARCH.md line 179: "Use this for Test Connection — no-side-effect
 * probe") with a 10s timeout and the bearer token decrypted from
 * `match_servers.credentials_encrypted`.
 *
 * Return shape (immutable):
 *
 *     ['status' => 'ok'|'error', 'error' => ?string, 'map_rotation' => ?array]
 *
 * Status mapping:
 *   - HTTP 200      → `ok`
 *   - HTTP 401      → `error` + `rcon.errors.auth_failed`
 *   - other HTTP    → `error` + `rcon.errors.unreachable`
 *   - timeout/conn  → `error` + `rcon.errors.unreachable` (Throwable catch)
 *   - missing token → `error` + `rcon.errors.permission_denied` (no HTTP call)
 *
 * T-08-09-04 mitigation: only translated keys + status returned — the raw
 * exception is never propagated to the audit trail or notification surface.
 */
final class CrconHealthProbe
{
    /**
     * @return array{status: 'ok'|'error', error: ?string, map_rotation: ?array<int|string, mixed>}
     */
    public function probe(MatchServer $server): array
    {
        /** @var array{api_token?: string}|null $credentials */
        $credentials = $server->credentials_encrypted;
        $token = $credentials['api_token'] ?? null;

        if ($token === null || $token === '') {
            return [
                'status' => 'error',
                'error' => __('rcon.errors.permission_denied'),
                'map_rotation' => null,
            ];
        }

        $url = sprintf('http://%s:%d/api/get_map_rotation', $server->host, $server->port_rcon);

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get($url);
        } catch (Throwable) {
            // ConnectionException (DNS/timeout/conn-refused) inherits Throwable.
            // Surface translated key only — raw exception never reaches caller
            // (T-08-09-04 mitigation).
            return [
                'status' => 'error',
                'error' => __('rcon.errors.unreachable'),
                'map_rotation' => null,
            ];
        }

        if ($response->failed()) {
            $errorKey = $response->status() === 401
                ? 'rcon.errors.auth_failed'
                : 'rcon.errors.unreachable';

            return [
                'status' => 'error',
                'error' => __($errorKey),
                'map_rotation' => null,
            ];
        }

        /** @var array<int|string, mixed>|null $rotation */
        $rotation = $response->json('result');

        return [
            'status' => 'ok',
            'error' => null,
            'map_rotation' => $rotation,
        ];
    }
}
