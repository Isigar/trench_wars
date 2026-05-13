<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
| Source: 06-04-PLAN.md Task 1 + 06-RESEARCH.md Pitfall 3 (bracket generator non-idempotency).
|
| Thrown by App\Services\Brackets\BracketGeneratorService::generate() (plan 06-06) when
| generate() is invoked against a tournament that already has tournament_stages rows.
|
| Shipped HERE in plan 06-04 — not in plan 06-06 where the producer lives — to break the
| circular dependency between TournamentStatusService (consumed by Filament admin actions
| in plan 06-11) and BracketGeneratorService (consumed inside the start() flow that the
| TournamentStatusService transition routes through). Forward-declaring the exception in
| plan 06-04 lets plan 06-06 `use` it without creating a same-wave cycle.
|
| Hierarchy choice: extends \DomainException — mirrors TournamentStatusInvalidTransitionException
| above + the Phase 4 MatchNotOpenException precedent. State-machine + idempotency violations
| are domain invariants, not runtime errors.
*/

final class BracketsAlreadyGeneratedException extends \DomainException {}
