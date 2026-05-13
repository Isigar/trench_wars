<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
| Source: 06-04-PLAN.md Task 1 + 06-RESEARCH.md Pattern 1 (Tournament status state machine).
|
| Thrown by App\Services\TournamentStatusService::transition() when the requested
| (from, to) status pair is not in the ALLOWED matrix:
|
|     draft       → registering | cancelled
|     registering → seeded      | cancelled
|     seeded      → running     | registering | cancelled
|     running     → completed   | cancelled
|     completed   → (terminal)
|     cancelled   → (terminal)
|
| Hierarchy choice: extends \DomainException — matches the Phase 4 MatchNotOpenException
| precedent verbatim (RESEARCH Pattern 2). Both Phase 4 (matches) and Phase 6 (tournaments)
| treat state-machine violations as domain-level invariants, not runtime errors.
|
| Threat refs: T-06-04-01 (invalid transition via service bypass) — mitigated here +
| at the DB layer via tournaments_status_check (plan 06-02).
*/

final class TournamentStatusInvalidTransitionException extends \DomainException {}
