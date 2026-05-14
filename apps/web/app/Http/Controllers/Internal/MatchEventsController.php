<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\StoreMatchEventsRequest;
use App\Models\GameMatch;
use App\Services\Rcon\MatchEventIngestService;
use Illuminate\Http\JsonResponse;

/**
 * Source: .planning/phases/08-rcon-automation/08-06-PLAN.md task 1 (route + shim)
 *         + 08-07-PLAN.md task 2 (real ingest service wiring — this iteration).
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
 * Wire contract (must_haves.truths #1, plan 08-06):
 *   202 Accepted + { batch_id, accepted_count, skipped_count } on success.
 *   422 with FormRequest validation errors on bad input array shape.
 *   404 if route binding fails (unknown match UUID).
 *   401 from middleware on signature failure.
 *   500 from controller when {@see MatchEventIngestService}'s normaliser
 *       throws `InvalidArgumentException` (operator alert — T-08-07-01).
 *
 * **Plan 08-07 replaced the Wave-4 shim with the real
 * {@see MatchEventIngestService}.** The service is `final` and container-bound;
 * Laravel resolves it via method injection on this action.
 *
 * The response shape gains a new `skipped_count` field versus the plan 08-06
 * shim's `{batch_id, accepted_count}`. This is additive — existing consumers
 * (plan 08-06's InternalApiRoutesPresentTest case 6 which only asserts
 * `accepted_count=1`) remain GREEN because `toHaveKeys` is non-strict.
 */
final class MatchEventsController extends Controller
{
    public function store(
        StoreMatchEventsRequest $request,
        GameMatch $match,
        MatchEventIngestService $service,
    ): JsonResponse {
        /** @var array{events: array<int, array<string, mixed>>} $validated */
        $validated = $request->validated();

        $result = $service->ingest($match, $validated['events']);

        return response()->json($result, 202);
    }
}
