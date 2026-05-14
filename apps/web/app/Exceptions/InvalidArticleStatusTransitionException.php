<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
| Source: 07-06-PLAN.md Task 1 + 07-RESEARCH.md (CMS state machine).
|
| Thrown by App\Services\ArticleStatusService::transition() when the requested
| (from, to) status pair is not in the permitted matrix:
|
|     draft     → scheduled | published
|     scheduled → published | draft
|     published → draft
|
| Hierarchy choice: extends \DomainException — matches Phase 4
| (MatchStatusInvalidTransitionException) + Phase 6
| (TournamentStatusInvalidTransitionException) precedents verbatim.
|
| Message rendered via __('cms.errors.invalid_status_transition'); the literal
| string passed to parent::__construct() is for logs only. User-facing surface
| flows through the i18n key at the controller catch layer (plan 07-09).
|
| Threat refs: T-07-06-01 (re-publish spam) mitigation at observer layer;
| this exception covers T-07-06-01 service-layer guard half.
*/

final class InvalidArticleStatusTransitionException extends \DomainException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(sprintf('Invalid Article status transition: %s -> %s', $from, $to));
    }
}
