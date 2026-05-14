<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\SearchResultData;
use App\Data\SearchResultsData;
use App\Models\Article;
use App\Models\Clan;
use App\Models\Player;
use App\Models\User;

/**
 * Source: .planning/phases/07-cms/07-08-PLAN.md task 1 + 07-RESEARCH.md Pattern 3.
 *
 * Postgres FTS UNION pipeline. Three parallel parameter-bound plainto_tsquery
 *
 * @@ search_vector predicates (one each for articles + clans + players),
 * each ts_rank-ordered DESC, capped at 20 rows per type. The PHP layer merges
 * the three Eloquent collections into a typed DTO and filters players through
 * PlayerPrivacyGate before returning (D-018 enforcement).
 *
 * Pitfall 2 mitigation (07-RESEARCH § Pitfall 2 — Postgres `to_tsquery()`
 * throws on user input): ALL three predicates use plainto_tsquery, which
 * collapses any input to AND'd lexemes after stripping operators. Stray
 * punctuation ('AC/DC', 'foo;DROP TABLE bar') sanitises cleanly without
 * raising QueryException. NEVER swap to_tsquery in here — that re-opens the
 * Pitfall 2 surface and lets SQL-injection-looking input crash the search
 * page for any reader.
 *
 * Parameter binding via the [?] placeholder in both whereRaw and orderByRaw
 * keeps the user-supplied $q strictly out of SQL string concatenation. The
 * planner reuses the bound plan; identical $q values are re-planned identically
 * across requests (PG plan cache + Eloquent connection reuse).
 *
 * Threat refs:
 *   - T-07-08-01 (SQL injection via $q): mitigated — parameter-bound placeholder
 *     in whereRaw + orderByRaw. Tested via `does NOT throw on punctuation-laden
 *     query` Pest assertion.
 *   - T-07-08-02 (Stray punctuation crashing to_tsquery): mitigated —
 *     plainto_tsquery used exclusively; never to_tsquery.
 *   - T-07-08-03 (Private-tier player leak): mitigated — players collection
 *     is filtered through PlayerPrivacyGate::canShowInSearch BEFORE the DTO
 *     factory is invoked.
 *   - T-07-08-05 (Draft article disclosure): mitigated — articles query chains
 *     ->where('status', 'published'), so the FTS index might list a draft
 *     row but the predicate filter excludes it. Tested via `excludes draft
 *     articles from results`.
 *   - T-07-08-06 (Unbounded result set): mitigated — each query has ->limit(20),
 *     total response capped at 60 rows; Postgres FTS GIN index lookups are
 *     sub-millisecond on the round-1 corpus.
 *   - T-07-08-08 (DoS via repeated complex queries): mitigation at HTTP layer
 *     (plan 07-09 wraps SearchController in throttle:60,1).
 */
final class SearchService
{
    public function __construct(private PlayerPrivacyGate $privacyGate) {}

    public function search(string $q, ?User $viewer = null): SearchResultsData
    {
        $q = trim($q);
        if ($q === '') {
            return SearchResultsData::forEmptyQuery($q);
        }

        $articles = Article::query()
            ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$q])
            ->where('status', 'published')
            ->orderByRaw("ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC", [$q])
            ->limit(20)
            ->get()
            ->values();

        $clans = Clan::query()
            ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$q])
            ->orderByRaw("ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC", [$q])
            ->limit(20)
            ->get()
            ->values();

        $players = Player::query()
            ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$q])
            ->orderByRaw("ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC", [$q])
            ->limit(20)
            ->get()
            ->filter(fn (Player $p): bool => $this->privacyGate->canShowInSearch($p, $viewer))
            ->values();

        // Convert each list to SearchResultData[]. rank is a 0-based descending
        // ordinal (DB-side ts_rank DESC ordering is already applied; this preserves
        // the total order without requiring a second SELECT for the float).
        /** @var array<int, SearchResultData> $articleResults */
        $articleResults = $articles
            ->map(fn (Article $a, int $i): SearchResultData => SearchResultData::fromArticle($a, (float) ($articles->count() - $i)))
            ->all();

        /** @var array<int, SearchResultData> $clanResults */
        $clanResults = $clans
            ->map(fn (Clan $c, int $i): SearchResultData => SearchResultData::fromClan($c, (float) ($clans->count() - $i)))
            ->all();

        /** @var array<int, SearchResultData> $playerResults */
        $playerResults = $players
            ->map(fn (Player $p, int $i): SearchResultData => SearchResultData::fromPlayer($p, $this->privacyGate, $viewer, (float) ($players->count() - $i)))
            ->all();

        return SearchResultsData::fromQuery($q, $articleResults, $clanResults, $playerResults);
    }
}
