<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 09-07 (every moderator action emits an
| activity_log row via LogsActivity trait). Asserts intent of SC-3 (audit)
| AND D-012 (Filament + spatie/activitylog audit infra).
|
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1.
| Validation Architecture row (09-RESEARCH.md L1350): "Every moderator action writes an activity_log row".
*/

test('Wave 0 stub: every moderator BulkAction and dispute transition writes an activity_log row', function (): void {
    expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-07');
})->skip('Wave 0 stub — turned GREEN in plan 09-07');
