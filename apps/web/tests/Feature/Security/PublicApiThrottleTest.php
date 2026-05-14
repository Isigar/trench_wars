<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-11 (public-api JSON endpoints attach
| throttle:public-api middleware → 30 reqs/min/IP → 429 on overage). Asserts
| intent of SC-5 (rate limit) — public-api throttle enforced.
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1361): "/clans.json throttled by public-api limiter (429 after 30 reqs)".
*/

test('Wave 0 stub: /clans.json is throttled by public-api limiter (429 after 30 reqs in one minute)', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-11');
})->skip('Wave 0 stub — turned GREEN in plan 09-11');
