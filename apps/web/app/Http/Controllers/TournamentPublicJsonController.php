<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PublicTournamentData;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-12-PLAN.md <interfaces>
 *         TournamentPublicJsonController + 06-RESEARCH.md Pattern 9 (30s polling
 *         with If-None-Match short-circuit).
 *
 * Public GET /tournaments/{slug}.json — JSON polling endpoint that powers the
 * Tournaments/Show page's 30s refresh loop. Reuses PublicTournamentData (the
 * same DTO Inertia ships on first paint) — single source of truth, no shape
 * drift between SSR and polling responses.
 *
 * Caching:
 *   - response()->setEtag($data->etag) emits a quoted ETag header.
 *   - If-None-Match request header is matched against the freshly-computed etag;
 *     when equal, a 204-like 304 NotModified short-circuits without re-serialising
 *     the full payload (T-06-12-01 amplification mitigation).
 *
 * Rate limiting:
 *   - Routed under Laravel `throttle:60,1` (60 req/min/IP) — see routes/web.php.
 *
 * Threat refs:
 *   - T-06-12-01 (hot DoS)                    — mitigated by throttle:60,1 + 304 short-circuit
 *   - T-06-12-02 (etag bypass)                — accept (rate limiter catches abusers)
 *   - T-06-12-03 (info disclosure)            — mitigated by PublicTournamentData privacy filter
 *   - T-06-12-04 (response leaks session cookies) — accept (JSON response, no session cookies emitted)
 */
class TournamentPublicJsonController extends Controller
{
    public function __invoke(Request $request, Tournament $tournament): JsonResponse
    {
        abort_unless($tournament->is_public, 404);

        $tournament->load([
            'stages.brackets.participantA.clan',
            'stages.brackets.participantB.clan',
            'stages.brackets.winnerParticipant.clan',
            'stages.brackets.match',
            'standings.participant.clan',
            'participants.clan',
        ]);

        $data = PublicTournamentData::fromModel($tournament);

        $clientEtag = $request->header('If-None-Match');
        if ($clientEtag !== null && trim((string) $clientEtag, '"') === $data->etag) {
            /** @var JsonResponse $notModified */
            $notModified = response()->json(null, 304);
            $notModified->setEtag($data->etag);

            return $notModified;
        }

        /** @var JsonResponse $response */
        $response = response()->json([
            'data' => $data,
            'etag' => $data->etag,
            'last_modified_at' => $data->last_modified_at,
        ]);
        $response->setEtag($data->etag);

        return $response;
    }
}
