<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
| Source: 10-01-PLAN.md Task 2 — CLAN-03 duplicate-pending guard failure mode.
|
| Thrown by ClanApplicationService::apply() (plan 10-02) when the applicant already
| has a pending ClanApplication for the same clan ((applicant_user_id, clan_id) uniqueness,
| enforced at the DB layer by the clan_applications_one_pending_per_clan partial unique index).
| Mapped to bot error code `duplicate_application` in BotApiClanApplicationController (plan 10-03).
|
| Hierarchy: extends \DomainException — mirrors Phase 4 MatchNotOpenException pattern
| (RESEARCH.md Pattern 2 / typed-exception → 422 mapping convention).
*/

final class DuplicateApplicationException extends \DomainException {}
