<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 08-05 (ValidateRconHmacSignature middleware
| + nonce store on Redis with 60s replay window). Asserts the CON-arch-rcon-to-web-comm
| contract: HMAC-SHA256 over (X-Rcon-Timestamp + raw body), Redis-backed nonce
| single-use, ±60s clock skew window, and 401 responses for bad/stale/replayed
| signatures. RESEARCH Pitfall 1 (sign RAW bytes) + Pitfall 2 (clock skew).
|
| Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
*/

test('ValidateRconHmacSignature rejects bad signature, stale timestamp, and replayed nonce', function (): void {
    expect(true)->toBeFalse();
});
