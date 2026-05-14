<?php

declare(strict_types=1);

namespace App\Data\Internal;

use App\Models\MatchServer;
use App\Models\MatchServerBooking;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/08-rcon-automation/08-06-PLAN.md <interfaces> BookingDueData.
 *
 * Wire-format OUTPUT shape for GET /api/internal/bookings/due. The rcon-worker's
 * BookingScheduler (plan 08-11) polls this endpoint every ~30s, receives the list,
 * and dispatches a CrconClient session per row (plan 08-10).
 *
 * **Timestamps are ISO-8601 strings** so the worker's `new Date(reserved_from)`
 * parses deterministically across JS runtimes.
 *
 * **Server host/port are PRE-RESOLVED** server-side from the eager-loaded
 * MatchServer relation. The worker does NOT make a second hop to
 * /api/internal/match-servers/{id}/credentials just to get host/port — that
 * endpoint is reserved for the api_token (decryption gated by HMAC + active flag).
 * Plan 08-10 calls credentials on session open AFTER it sees a row from this endpoint.
 *
 * `#[TypeScript]` (D-020) — plan 08-12 regenerates packages/shared-types and the
 * worker imports `import type { BookingDueData } from '@trench-wars/shared-types'`.
 */
#[TypeScript]
final class BookingDueData extends Data
{
    public function __construct(
        public string $id,
        public string $match_id,
        public string $server_id,
        public string $server_host,
        public int $server_port,
        public string $reserved_from,
        public string $reserved_to,
    ) {}

    /**
     * Build a BookingDueData from a MatchServerBooking with the `server` relation
     * eager-loaded. Caller MUST `->with('server')` before invoking; this method
     * does NOT lazy-load to keep the BookingScheduleController query N+1-safe.
     */
    public static function fromModel(MatchServerBooking $booking): self
    {
        /** @var MatchServer $server */
        $server = $booking->server;

        /** @var Carbon $reservedFrom */
        $reservedFrom = $booking->reserved_from;

        /** @var Carbon $reservedTo */
        $reservedTo = $booking->reserved_to;

        return new self(
            id: $booking->id,
            match_id: $booking->match_id,
            server_id: $booking->server_id,
            server_host: $server->host,
            server_port: $server->port_rcon,
            reserved_from: $reservedFrom->toIso8601String(),
            reserved_to: $reservedTo->toIso8601String(),
        );
    }
}
