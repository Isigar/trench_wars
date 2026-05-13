<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
| Source: 04-06-PLAN.md Task 1 + 04-RESEARCH.md Pattern 2 (MatchSignupService).
|
| Thrown by MatchSignupService::signup() when the user already occupies a
| slot in the same match (one-slot-per-user-per-match idempotency check —
| holds even across different game_role_ids). The partial UNIQUE index on
| match_slots(match_id, occupant_user_id) WHERE occupant_user_id IS NOT NULL
| (plan 04-02) is the DB-layer guard; this exception is the application-layer
| guard that produces a friendly 422 response instead of a Postgres
| constraint-violation error.
|
| Hierarchy: extends \DomainException — matches the MatchSignupService
| exception family.
|
| Threat refs: T-04-06-04 (Tampering — mass-assignment on
| MatchSlot.occupant_user_id; the service is the single production write
| path and the idempotency check + DB partial UNIQUE jointly enforce the
| invariant).
*/

final class AlreadySignedUpException extends \DomainException {}
