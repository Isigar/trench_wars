<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PublicMatchData;
use App\Models\ClanTag;
use App\Models\GameMatch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: 04-10-PLAN.md Task 1 + 04-RESEARCH.md Pattern 7 (calendar half).
 *
 * Public GET /matches — match calendar. No auth required (SC-3 first half).
 *
 * Filtering:
 *   ?date_from=YYYY-MM-DD   (default: today)
 *   ?date_to=YYYY-MM-DD     (optional upper bound)
 *   ?tag=<clan_tag_slug>    (only matches with that allowlisted access rule)
 *   ?status=open|locked|played
 *
 * Visibility:
 *   - is_public = true                       (private matches hidden from public)
 *   - status NOT IN ('draft', 'cancelled')   (draft & cancelled hidden from public)
 *   - scheduled_at >= date_from              (future-only by default)
 *
 * Security:
 *   T-04-10-06 (DoS — unbounded scan): paginate(20).
 *   T-04-10-07 (SQL injection via filters): Eloquent parameter-bound queries +
 *     validated input shapes (alpha_dash, date, in:enum).
 *   T-04-07-05 (cancelled/draft visibility): the visibility WHERE clause filters
 *     at the query layer — PublicMatchData itself is a shape, not a filter.
 *   T-04-10-08 (server_address leak): PublicMatchData omits server_address.
 */
class MatchCalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'tag' => 'nullable|string|alpha_dash|max:32',
            'status' => 'nullable|in:open,locked,played',
        ]);

        $dateFrom = $validated['date_from'] ?? today()->toDateString();
        $dateTo = $validated['date_to'] ?? null;
        $tag = $validated['tag'] ?? null;
        $status = $validated['status'] ?? null;

        $query = GameMatch::query()
            ->with(['gameMatchType', 'event', 'slots'])
            ->where('is_public', true)
            ->whereIn('status', ['open', 'locked', 'played'])
            ->whereDate('scheduled_at', '>=', $dateFrom);

        if ($dateTo !== null) {
            $query->whereDate('scheduled_at', '<=', $dateTo);
        }

        if ($tag !== null) {
            // T-04-10-07: tag resolved via Eloquent parameter-bound lookup.
            $tagModel = ClanTag::where('slug', $tag)->firstOrFail();
            $query->whereHas('accessRules', fn ($q) => $q->where('clan_tag_id', $tagModel->id));
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $paginator = $query->orderBy('scheduled_at')->paginate(20);

        return Inertia::render('Matches/Index', [
            'matches' => $paginator->getCollection()
                ->map(fn (GameMatch $m) => PublicMatchData::fromModel($m))
                ->values()
                ->all(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'perPage' => $paginator->perPage(),
            ],
            'activeFilters' => [
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'tag' => $tag,
                'status' => $status,
            ],
        ]);
    }
}
