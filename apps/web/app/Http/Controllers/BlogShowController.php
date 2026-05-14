<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PublicArticleData;
use App\Models\Article;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: .planning/phases/07-cms/07-09-PLAN.md <interfaces> BlogShowController
 *         verbatim + ArticlePolicy (07-04) gate.
 *
 * Public GET /blog/{slug} — Inertia 'Articles/Show' page. No auth required for
 * published articles (SC-2 public surface); drafts/scheduled return 404 for
 * anonymous + non-editor visitors (T-07-09-02 — non-disclosure idiom).
 *
 * Why 404, not 401/403:
 *   The RESEARCH NFR is "don't leak existence". 401/403 would tell an attacker
 *   "this slug exists but you can't see it"; 404 is indistinguishable from a
 *   never-published slug. Mirrors MatchShowController + TournamentShowController
 *   precedent (T-04-10-02 / T-06-12-03).
 *
 * The abort_unless check mirrors ArticlePolicy::view() — published OR actor can
 * articles.update. We could call Gate::authorize('view', $article) instead, but
 * that throws AuthorizationException (403) by default; the inline check lets us
 * choose the 404 status explicitly without subclassing the policy.
 *
 * Eager loading: category + author + media — PublicArticleData::fromModel needs
 * all three (categoryName, authorName, heroThumbUrl/heroOgImageUrl).
 */
class BlogShowController extends Controller
{
    public function __invoke(Request $request, string $slug): Response
    {
        $article = Article::query()->where('slug', $slug)->firstOrFail();

        abort_unless(
            $article->status === 'published' || $request->user()?->can('articles.update'),
            404
        );

        $article->load('category', 'author', 'media');

        return Inertia::render('Articles/Show', [
            'article' => PublicArticleData::fromModel($article),
        ]);
    }
}
