<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
| Source: 04-06-PLAN.md Task 1 + 04-RESEARCH.md Pattern 5 (tag access allowlist).
|
| Thrown by MatchSignupService::signup() when the match has one or more
| match_access_rules and the signing-up user's active clan does NOT carry
| any of the allowlisted clan_tag_ids. The empty-rules case is "open to
| all" and never throws this exception (Pattern 5 semantics).
|
| Hierarchy: extends \DomainException — matches the MatchSignupService
| exception family (MatchNotOpenException, CapacityExceededException,
| AlreadySignedUpException).
|
| Threat refs: T-04-06-02 (Elevation of Privilege — tag-access bypass).
| The check is server-side; the UI signup button is never trusted as the
| authoritative gate. Maps to SC-5 (tag access enforcement).
*/

final class TagRestrictedException extends \DomainException {}
