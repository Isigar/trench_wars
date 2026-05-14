<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 08-07 (web-side normaliser contract check).
| Asserts the wire contract between worker's CrconEventNormaliser (TS, plan 08-10)
| and web's MatchEventIngestService (PHP, plan 08-07): every CRCON action that
| should land in match_events maps to ONE of the canonical match_event_type values
| listed in lang/en/rcon.php events.types.*. Drift is a CI failure — adding a new
| event type requires updating BOTH sides + the i18n keys.
|
| Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
*/

test('every match_event.event_type value resolves to a known lang/en/rcon.php events.types.* key', function (): void {
    expect(true)->toBeFalse();
});
