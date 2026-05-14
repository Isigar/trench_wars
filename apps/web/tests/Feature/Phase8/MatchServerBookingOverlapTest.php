<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 08-02 migration + plan 08-03 model. Asserts
| that Postgres EXCLUDE constraint on match_server_bookings prevents two bookings
| of the same server with overlapping reserved_from..reserved_to ranges (SC-2,
| REQ-constraint-league-owns-servers). Reserved window is
| [scheduled_start − 5m, scheduled_end + 30m].
|
| Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
*/

test('overlapping MatchServerBooking on same server raises PDOException via EXCLUDE constraint', function (): void {
    expect(true)->toBeFalse();
});
