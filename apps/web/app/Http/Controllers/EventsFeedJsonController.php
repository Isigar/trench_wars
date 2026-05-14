<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EventsFeedRequest;
use App\Models\Event;
use App\Services\CalendarFeedService;
use Illuminate\Http\JsonResponse;

/**
 * Source: .planning/phases/07-cms/07-09-PLAN.md <interfaces> EventsFeedJsonController
 *         verbatim + 07-RESEARCH.md Pattern 7.
 *
 * Public GET /events/feed.json — JSON feed consumed by FullCalendar on
 * /events. Returns up to 1000 events whose starts_at falls in the
 * [start, end] window AND is_public=true. Per-event payload is shaped by
 * CalendarEventData (id/title/start/end/type/url/color).
 *
 * Why 1000 cap (->limit(1000)):
 *   FullCalendar can render thousands of events in a single view but the
 *   browser-side DOM cost is meaningful past ~500. The cap is a defence-in-
 *   depth complement to the EventsFeedRequest range cap (90 days max) — even
 *   if a future change widens the range limit, the row limit holds.
 *   T-07-09-04 amplification mitigation.
 *
 * Why eager-load eventable:
 *   CalendarEventData::fromModel switches on `$event->eventable instanceof X`
 *   to resolve the type discriminator + URL. Without ->with('eventable'),
 *   each row would trigger one extra SELECT against the (polymorphic) owner
 *   table — N+1 disaster on a 1000-row feed.
 *
 * Route wiring (routes/web.php):
 *   - Route registered BEFORE /events so Laravel's first-match-wins router
 *     does not capture the .json suffix as a slug parameter.
 *     Phase 6 D-06-12-C precedent.
 *   - Routed under throttle:60,1 (T-07-09-01 mitigation).
 */
class EventsFeedJsonController extends Controller
{
    public function __invoke(EventsFeedRequest $request, CalendarFeedService $service): JsonResponse
    {
        /** @var string $start */
        $start = $request->validated('start');
        /** @var string $end */
        $end = $request->validated('end');

        $events = Event::query()
            ->where('is_public', true)
            ->whereBetween('starts_at', [$start, $end])
            ->with('eventable')
            ->limit(1000)
            ->get();

        return response()->json($service->toCalendarEvents($events));
    }
}
