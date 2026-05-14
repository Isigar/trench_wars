<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Data\Internal\BookingDueData;
use App\Http\Controllers\Controller;
use App\Models\MatchServerBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Source: .planning/phases/08-rcon-automation/08-06-PLAN.md task 1.
 *
 * GET /api/internal/bookings/due — fed to the rcon-worker's BookingScheduler
 * (plan 08-11) which polls every ~30s and spawns a CrconClient session per row.
 *
 * Auth: rcon.signature middleware (plan 08-05) is the trust principal.
 *
 * Wire contract (must_haves.truths #2):
 *   200 + array of BookingDueData rows.
 *   Active bookings only (status='active' — cancelled/completed excluded).
 *   Window: reserved_from <= (now + 5min) AND reserved_to >= (now - 5min) —
 *     half-open semantics matching MatchServerBooking::scopeDueWithin. The ±5min
 *     buffer covers worker poll-interval drift and graceful session ramp-up.
 *   `server` relation eager-loaded — N+1-safe; one query per poll.
 *
 * The 5-minute window is plan-spec (line 159 — `->dueWithin(now()->subMinutes(5),
 * now()->addMinutes(5))`). Plan 08-11 will calibrate exact bounds against worker
 * tick interval; this Wave 4 controller honours the canonical shape.
 */
final class BookingScheduleController extends Controller
{
    public function dueNow(): JsonResponse
    {
        $from = Carbon::now()->subMinutes(5);
        $to = Carbon::now()->addMinutes(5);

        /** @var Collection<int, MatchServerBooking> $bookings */
        $bookings = MatchServerBooking::query()
            ->active()
            ->dueWithin($from, $to)
            ->with('server')
            ->get();

        $data = $bookings
            ->map(fn (MatchServerBooking $booking): BookingDueData => BookingDueData::fromModel($booking))
            ->values()
            ->all();

        return response()->json($data);
    }
}
