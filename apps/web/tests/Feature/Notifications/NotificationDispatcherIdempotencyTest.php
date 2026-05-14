<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-04 (NotificationDispatcher idempotency
| guard via dispatch_log lookup). Asserts intent of SC-1 (dispatcher idempotency)
| — re-running cron does not duplicate notifications.
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1338): "Re-running cron does not duplicate notifications".
*/

test('Wave 0 stub: re-running dispatcher cron does not duplicate notifications', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-04');
})->skip('Wave 0 stub — turned GREEN in plan 09-04');
