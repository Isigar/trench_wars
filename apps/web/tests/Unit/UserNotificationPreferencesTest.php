<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-03 (User::enabledNotificationChannels
| method reads user_notification_preferences pivot). Asserts intent of SC-1
| (preferences honour user opt-outs per notification type).
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1339): "User->enabledNotificationChannels(...) honours user_notification_preferences".
*/

test('Wave 0 stub: User->enabledNotificationChannels honours user_notification_preferences', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-03');
})->skip('Wave 0 stub — turned GREEN in plan 09-03');
