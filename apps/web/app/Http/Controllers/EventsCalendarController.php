<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: .planning/phases/07-cms/07-09-PLAN.md task 1 + must_haves truths line 32.
 *
 * Public GET /events — Inertia 'Events/Index' shell that mounts FullCalendar
 * client-side. No auth required (SC-3 public surface). NO event payload is
 * shipped on this response — FullCalendar issues its own per-view AJAX fetch
 * to /events/feed.json with start+end query params (Pattern 7).
 *
 * Why no event data on the SSR response:
 *   FullCalendar paginates by view (month / week / day) and re-fetches when
 *   the user navigates between views. Embedding a snapshot of "events around
 *   today" on the SSR response would be wasted bandwidth (the client refetches
 *   immediately on mount) AND wrong shape (FullCalendar consumes its events
 *   prop as raw JSON, not Inertia props).
 *
 * The `categories` prop seeds the sidebar filter that lets users hide the
 * article-typed calendar entries (match + tournament filters are reserved for
 * a future plan once category-style match-type tags ship — see 07-CONTEXT.md
 * deferred-items).
 */
class EventsCalendarController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Events/Index', [
            'categories' => Category::query()->orderBy('slug')->get()->map(fn (Category $c): array => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->getTranslation('name', app()->getLocale(), useFallbackLocale: true),
            ])->all(),
            'meta' => [
                'title' => __('cms.page_meta.events.title'),
                'description' => __('cms.page_meta.events.description'),
            ],
        ]);
    }
}
