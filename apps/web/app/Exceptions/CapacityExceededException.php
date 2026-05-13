<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
| Source: 04-06-PLAN.md Task 1 + 04-RESEARCH.md Pattern 2 (MatchSignupService).
|
| Thrown by MatchSignupService::signup() when the targeted role is at full
| capacity. The signup controller (plan 04-10) catches this and converts to
| a 422 response carrying the localized matches.signup.error.capacity_full
| message.
|
| Hierarchy: extends \DomainException — matches MatchNotOpenException
| (plan 04-04) and the rest of the MatchSignupService exception family.
|
| Threat refs: T-04-06-01 (Tampering — CRITICAL — SC-2 capacity bypass via
| concurrent signups). The exception is the application-layer signal that
| the row-locked transaction guard fired correctly when the COUNT(occupied)
| reached COUNT(total) for the (match, role) pair.
*/

final class CapacityExceededException extends \DomainException {}
