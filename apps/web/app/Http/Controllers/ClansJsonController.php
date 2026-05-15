<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Clan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Source: .planning/phases/09-polish/09-11-PLAN.md task 1 must_haves —
 * "/clans.json carries throttle:public-api" + PublicApiThrottleTest fixture.
 *
 * Public GET /clans.json — slim JSON list of active clans for external
 * consumers (analytics, scraper-friendly index). The Inertia HTML directory
 * already lives at /clans (ClanDirectoryController); this endpoint exists so
 * the SC-5 public-api throttle has a JSON-shaped fixture and so future
 * machine-readable consumers (Discord bot enrichment, static-site exports)
 * have a stable URL to hit without rendering Vue.
 *
 * Route lives BEFORE /clans/{clan:slug} so Laravel's first-match-wins
 * dispatcher does not bind `clans.json` as a slug. Same precedent as Phase 6
 * /tournaments/{slug}.json (D-06-12-C) and Phase 7 /events/feed.json.
 *
 * Privacy: only active clans surface here — soft-deleted, disbanded, and
 * draft rows are filtered out at the query layer (consistent with
 * ClanDirectoryController L37 `where status active`).
 *
 * Threat refs:
 *   - T-09-11-01 (D — mass scraping) — mitigated by throttle:public-api
 *     (30/min by IP) attached at the route layer.
 *   - T-09-11-02 (S — X-Forwarded-For spoofing of IP-keyed throttle) —
 *     Laravel TrustProxies sets $request->ip() from the trusted upstream;
 *     Railway terminates TLS. The limiter relies on that.
 */
final class ClansJsonController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $clans = Clan::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'slug', 'tag', 'name', 'country_code', 'status']);

        return response()->json([
            'data' => $clans->map(static fn (Clan $clan): array => [
                'id' => $clan->id,
                'slug' => $clan->slug,
                'tag' => $clan->tag,
                'name' => $clan->name,
                'country_code' => $clan->country_code,
                'status' => $clan->status,
            ])->all(),
            'count' => $clans->count(),
        ]);
    }
}
