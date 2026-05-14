<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-04 (NotificationDispatcher::sweepUpcoming
| cron schedule). Asserts intent of SC-1 (dispatcher cron) — fires at T-60min
| and T-15min for booked matches.
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1337): "NotificationDispatcher::sweepUpcoming dispatches at T-60min and T-15min".
*/

test('Wave 0 stub: NotificationDispatcher::sweepUpcoming dispatches at T-60min and T-15min', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-04');
})->skip('Wave 0 stub — turned GREEN in plan 09-04');
