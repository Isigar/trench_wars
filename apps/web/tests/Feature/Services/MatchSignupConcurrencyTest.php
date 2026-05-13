<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — flipped GREEN by plan 04-06 (Wave 3: MatchSignupService row-locked
| capacity, SC-2 concurrency edge case — two players race for the last slot, exactly
| one succeeds with HTTP 201 and one gets HTTP 409 capacity_full).
|
| Source: 04-RESEARCH.md Pitfall 4 + Assumption A8 — pcntl extension required.
| Run `docker compose exec web php -m | grep pcntl` to verify availability before
| implementing this test in plan 04-06; if absent, fall back to dual-connection
| DB::connection alias approach per Pitfall 4 option 2.
|
| Verified during 04-01: `docker compose exec web php -m | grep pcntl` -> `pcntl`
| extension is present in the web container (Assumption A8 confirmed; primary
| pcntl_fork() approach is viable, fallback unnecessary on this image).
*/

it('placeholder — replace in plan 04-06', function (): void {
    $this->markTestIncomplete('Wave 0 RED stub — implementation in plan 04-06.');
});
