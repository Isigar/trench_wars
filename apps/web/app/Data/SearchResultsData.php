<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/07-cms/07-08-PLAN.md task 1.
 *
 * Aggregate DTO returned by SearchService::search(); consumed by SearchController
 * (plan 07-09) and the Vue Results page (plan 07-10) via Inertia + Spatie
 * Data's #[TypeScript] auto-generated type. The three per-type arrays preserve
 * the FTS ts_rank ordering (oldest list head = highest rank).
 *
 * `totalCount` is the sum of the three lists' lengths — kept on the DTO so
 * Vue can render "X results" without re-summing on the client.
 *
 * `query` carries the original (trimmed) user query string so the page can
 * echo "Results for: …" without a separate prop.
 *
 * The two named constructors mirror the SearchService dispatch:
 *   - forEmptyQuery($q): zero-cost zero-row return when the user submitted
 *     whitespace (SearchService short-circuits before firing any SQL —
 *     verified by the `returns empty SearchResultsData for empty query` Pest
 *     test which asserts the query log stays at 0). Renamed from `empty()`
 *     in the plan's <interfaces> sample to avoid colliding with
 *     Spatie\LaravelData\Data::empty(): array, which would force a non-
 *     covariant override (PHPStan parameter.notOptional + method.childReturnType
 *     errors). Recorded as Rule 3 deviation in SUMMARY.
 *   - fromQuery($q, $articles, $clans, $players): builds the aggregate from
 *     the three per-type arrays the service merged. totalCount is computed
 *     server-side, not a client responsibility.
 */
#[TypeScript]
final class SearchResultsData extends Data
{
    /**
     * @param  array<int, SearchResultData>  $articles
     * @param  array<int, SearchResultData>  $clans
     * @param  array<int, SearchResultData>  $players
     */
    public function __construct(
        public array $articles,
        public array $clans,
        public array $players,
        public int $totalCount,
        public string $query,
    ) {}

    /**
     * Zero-row return for an empty/whitespace query. SearchService short-circuits
     * to this without issuing any SQL.
     *
     * Renamed from `empty()` to avoid colliding with Spatie\LaravelData\Data::empty(),
     * which returns array — overriding with a `self` return type triggers
     * PHPStan's method.childReturnType + parameter.notOptional errors.
     */
    public static function forEmptyQuery(string $q): self
    {
        return new self(
            articles: [],
            clans: [],
            players: [],
            totalCount: 0,
            query: $q,
        );
    }

    /**
     * @param  array<int, SearchResultData>  $articles
     * @param  array<int, SearchResultData>  $clans
     * @param  array<int, SearchResultData>  $players
     */
    public static function fromQuery(string $q, array $articles, array $clans, array $players): self
    {
        return new self(
            articles: $articles,
            clans: $clans,
            players: $players,
            totalCount: count($articles) + count($clans) + count($players),
            query: $q,
        );
    }
}
