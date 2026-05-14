<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CalendarEventData;
use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * Source: .planning/phases/07-cms/07-09-PLAN.md task 1 + must_haves truths line 37.
 *
 * Pure mapper that turns an eager-loaded Collection<Event> into an array of
 * CalendarEventData rows for /events/feed.json. No extra DB queries beyond
 * what the caller (EventsFeedJsonController) already eager-loaded via
 * ->with('eventable').
 *
 * Pattern 7 morphTo resolution lives on CalendarEventData::fromModel — this
 * service is a thin orchestration layer kept separate from the DTO so:
 *   1. The mapping logic is unit-testable without spinning up an HTTP request.
 *   2. Future plans that need the same shape (e.g. an ICS feed in Phase 9)
 *      can call $service->toCalendarEvents() without re-implementing the
 *      type/colour/url switch.
 *
 * The service is declared `final` to discourage subclass overrides — calendar
 * shaping is a single-responsibility concern; if a future caller needs a
 * different shape, it should compose a new service rather than override this
 * one.
 */
final class CalendarFeedService
{
    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, CalendarEventData>
     */
    public function toCalendarEvents(Collection $events): array
    {
        /** @var array<int, CalendarEventData> $rows */
        $rows = $events
            ->map(fn (Event $e): CalendarEventData => CalendarEventData::fromModel($e))
            ->values()
            ->all();

        return $rows;
    }
}
