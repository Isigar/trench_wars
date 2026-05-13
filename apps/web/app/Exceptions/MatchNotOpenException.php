<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
| Source: 04-04-PLAN.md Task 1 + 04-RESEARCH.md Pattern 2 (MatchSignupService consumer).
|
| Thrown by MatchSignupService (plan 04-06) when an attempted signup targets a match
| whose status is not 'open' (i.e. draft / locked / played / cancelled). The exception
| class is defined here, in plan 04-04, so plan 04-06 can import it without creating a
| circular dependency between the signup service and the status service.
|
| Hierarchy choice: extends \DomainException — matches RESEARCH Pattern 2 verbatim
| (all four MatchSignupService exceptions are DomainException subclasses). Phase 2's
| ReservedSlugException extends RuntimeException, but that's a different domain
| (slug generation) and does not bind Phase 4 by precedent.
|
| Threat refs: T-04-06-* (signup gate bypass — to be detailed in plan 04-06 threat model).
*/

final class MatchNotOpenException extends \DomainException {}
