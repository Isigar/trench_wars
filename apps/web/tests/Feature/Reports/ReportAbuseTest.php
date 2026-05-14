<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-11 (ReportAbuseController creates
| abuse_reports row + writes activity_log entry; respects polymorphic target).
| Asserts intent of SC-5 (report abuse).
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1362): "POST /reports creates abuse_reports row + activity_log entry".
*/

test('Wave 0 stub: POST /reports creates abuse_reports row + activity_log entry', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-11');
})->skip('Wave 0 stub — turned GREEN in plan 09-11');
