<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-11 (report-abuse limiter blocks 6th
| report from the same user within a rolling 1-hour window). Asserts intent
| of SC-5 (report abuse throttle).
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1363): "report-abuse limiter blocks 6th report in 1 hour".
*/

test('Wave 0 stub: report-abuse limiter blocks 6th report from same user within 1 hour', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-11');
})->skip('Wave 0 stub — turned GREEN in plan 09-11');
