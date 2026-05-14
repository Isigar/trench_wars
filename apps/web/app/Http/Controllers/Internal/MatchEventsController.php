<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\StoreMatchEventsRequest;
use App\Models\GameMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Source: .planning/phases/08-rcon-automation/08-06-PLAN.md task 1.
 *
 * POST /api/internal/match/{match}/events — worker (plan 08-10) → web ingest.
 *
 * Auth: rcon.signature middleware (plan 08-05) is the trust principal — HMAC over
 * (timestamp + raw body) verified before this controller ever runs. NEVER trusts
 * request body fields for identity.
 *
 * Route binding: `{match}` resolves to App\Models\GameMatch via implicit binding on
 * UUID `id` column (D-04-03-A LOCKED naming — `GameMatch`, not `Match` which is a
 * reserved PHP keyword). 404 on unknown UUID is automatic.
 *
 * Wire contract (must_haves.truths #1):
 *   202 Accepted + { batch_id: <uuid>, accepted_count: <int> } on success.
 *   422 with FormRequest validation errors on bad input.
 *   404 if route binding fails (unknown match UUID).
 *   401 from middleware on signature failure.
 *
 * **Wave 4 SHIM** — this controller currently returns a synthetic batch_id WITHOUT
 * persisting events. Plan 08-07 lands MatchEventIngestService and refactors this
 * controller to inject + call `$service->ingest($match, $events)`. The shim
 * accepts the events so plan 08-10 (worker outbound) can develop against a stable
 * 202-returning target; persistence semantics arrive in 08-07.
 *
 * TODO(plan 08-07): inject MatchEventIngestService; replace inline shim with
 *     `$batchId = $service->ingest($match, $validated['events']);`.
 */
final class MatchEventsController extends Controller
{
    public function store(StoreMatchEventsRequest $request, GameMatch $match): JsonResponse
    {
        /** @var array{events: array<int, array<string, mixed>>} $validated */
        $validated = $request->validated();

        // TODO(plan 08-07): replace shim with MatchEventIngestService::ingest($match, $events).
        // The shim mints a UUID batch identifier and echoes accepted_count for plan 08-10
        // worker development. Plan 08-07 will replace this with idempotent per-event
        // creates against MatchEvent + the composite UNIQUE (match_id, crcon_stream_id).
        $batchId = (string) Str::uuid();
        $acceptedCount = count($validated['events']);

        // Silence unused-var lint until 08-07 wires the service.
        unset($match);

        return response()->json([
            'batch_id' => $batchId,
            'accepted_count' => $acceptedCount,
        ], 202);
    }
}
