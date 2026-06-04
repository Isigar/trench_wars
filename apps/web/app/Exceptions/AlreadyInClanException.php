<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
| Source: 10-01-PLAN.md Task 2 — CLAN-03 active-membership guard failure mode.
|
| Thrown by ClanApplicationService::apply() (plan 10-02) when the applicant already
| holds an active ClanMembership (left_at IS NULL — D-009 one-active invariant).
| Mapped to bot error code `already_in_clan` in BotApiClanApplicationController (plan 10-03).
|
| Hierarchy: extends \DomainException — mirrors Phase 4 MatchNotOpenException pattern
| (RESEARCH.md Pattern 2 / typed-exception → 422 mapping convention).
*/

final class AlreadyInClanException extends \DomainException {}
