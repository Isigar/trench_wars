<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
| Source: 06-05-PLAN.md Task 1 + 06-RESEARCH.md Open Question A4 (RESOLVED inline).
|
| Thrown by App\Services\TournamentSeedingService::reseed() when
| App\Models\Tournament::canReseed() returns false (a MatchResult row already exists
| for at least one bracket-linked match in the tournament, OR the tournament is not
| in the 'seeded' status).
|
| Open Question A4 LOCKED at the strictest reasonable threshold: once a result is
| recorded, reseeding would invalidate played work — admin must `cancel` and create
| a new tournament instead.
|
| Hierarchy choice: extends \DomainException — matches the Phase 4
| MatchNotOpenException + Phase 6 TournamentStatusInvalidTransitionException
| precedents verbatim. State-machine + idempotency violations are domain-level
| invariants, not runtime errors.
|
| Threat refs: T-06-05-01 (Tampering — admin reseeds after results recorded;
| standings retroactively invalid) — mitigated here.
*/

final class SeedingNotAllowedException extends \DomainException {}
