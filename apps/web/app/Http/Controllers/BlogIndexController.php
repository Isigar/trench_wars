<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ArticleSummaryData;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: .planning/phases/07-cms/07-09-PLAN.md task 1 + must_haves truths line 30.
 *
 * Public GET /blog — paginated listing of published articles. No auth required
 * (SC-2 public surface).
 *
 * Filtering:
 *   ?category=<slug>   — restrict to articles whose category has the given slug
 *   ?page=N            — Laravel paginator standard
 *
 * Visibility:
 *   - status = 'published'                — drafts + scheduled never surface here
 *     (T-07-09-02 mitigation — defence-in-depth alongside BlogShowController's
 *     abort_unless on individual show pages)
 *   - ORDER BY published_at DESC          — newest first
 *
 * Eager-loading: category + author + media (Pattern 2 thumb conversion). Without
 * `media` in the with-list, each ArticleSummaryData::fromModel call would fire
 * an extra getFirstMediaUrl SELECT per row (N+1 rule).
 *
 * Pagination: 15 per page — fits a 3-wide grid layout at desktop with 5 rows.
 */
class BlogIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'category' => 'nullable|string|alpha_dash|max:64',
        ]);

        $categorySlug = $validated['category'] ?? null;

        $query = Article::query()
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->with(['category', 'author', 'media']);

        if ($categorySlug !== null && $categorySlug !== '') {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        $paginator = $query->paginate(15)->withQueryString();

        $articles = $paginator
            ->getCollection()
            ->map(fn (Article $a): ArticleSummaryData => ArticleSummaryData::fromModel($a))
            ->values()
            ->all();

        return Inertia::render('Articles/Index', [
            'articles' => $articles,
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'perPage' => $paginator->perPage(),
            ],
            'categories' => Category::query()->orderBy('slug')->get()->map(fn (Category $c): array => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->getTranslation('name', app()->getLocale(), useFallbackLocale: true),
            ])->all(),
            'activeCategory' => $categorySlug,
            'meta' => [
                'title' => __('cms.page_meta.blog_index.title'),
                'description' => __('cms.page_meta.blog_index.description'),
            ],
        ]);
    }
}
