<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
| Source: 10-01-PLAN.md Task 2 — CLAN-04 recruiting toggle failure mode.
|
| Thrown by ClanApplicationService::apply() (plan 10-02) when the target clan has
| accepts_applications = false. Mapped to bot error code `clan_not_recruiting` in
| BotApiClanApplicationController (plan 10-03).
|
| Hierarchy: extends \DomainException — mirrors Phase 4 MatchNotOpenException pattern
| (RESEARCH.md Pattern 2 / typed-exception → 422 mapping convention).
*/

final class ClanNotRecruitingException extends \DomainException {}
