<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Services\SearchService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: .planning/phases/07-cms/07-09-PLAN.md task 1 + must_haves truths line 33.
 *
 * Public GET /search?q= — Inertia 'Search/Results' page. No auth required
 * (SC-4 public surface). PlayerPrivacyGate runs inside SearchService so
 * private-tier players are filtered out before reaching the controller
 * (D-018 enforcement).
 *
 * The query string is validated by SearchRequest:
 *   - required, min:2, max:200
 *   - regex limited to letters/numbers/spaces + safe punctuation
 *     (T-07-09-03 mitigation — first sanitisation layer; plainto_tsquery
 *     inside SearchService is the second)
 *
 * Rate limiting (throttle:60,1) is applied at the routes/web.php layer per
 * Phase 6 D-06-12-A precedent (T-07-09-01 mitigation).
 *
 * Reflected XSS (T-07-09-06): Inertia ships the `query` prop as JSON; Vue's
 * default text interpolation (`{{ }}`) and v-text escape HTML automatically.
 * The Pest test asserts the raw `<script>` tag never appears in the response
 * body (no v-html usage on the query echo).
 */
class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, SearchService $service): Response
    {
        /** @var string $q */
        $q = $request->validated('q');

        $results = $service->search($q, $request->user());

        return Inertia::render('Search/Results', [
            'results' => $results,
            'query' => $q,
            'meta' => [
                'title' => __('cms.page_meta.search.title', ['query' => $q]),
                'description' => __('cms.page_meta.search.description'),
            ],
        ]);
    }
}
