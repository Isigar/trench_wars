<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 08-07 (MatchEventIngestService idempotent
| upsert keyed on (match_id, crcon_stream_id)). RESEARCH Pitfall 3 mitigation:
| CRCON `/ws/logs` may return logs predating the booking on reconnect, and the
| worker may resend events after a network blip — the unique index + upsert
| guarantees at-most-once persistence regardless of duplicate POSTs.
|
| Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
*/

test('duplicate POST of same (match_id, crcon_stream_id) is idempotent — second insert is a no-op', function (): void {
    expect(true)->toBeFalse();
});
