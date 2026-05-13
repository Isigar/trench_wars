<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-08-PLAN.md Task 1 +
 *         06-RESEARCH.md Pattern 7 (bracket advancement).
 *
 * Thrown by App\Services\BracketAdvancementService::advance() when
 * MatchResult.winner_clan_id has no matching tournament_participants row
 * for the tournament owning the bracket.
 *
 * This is a DB integrity guard — normally the materialiser ensures
 * match.host_clan + signups come from registered participants; this
 * exception fires only if data is corrupt or admin manually edited a
 * bracket-linked match's result with a foreign clan_id.
 *
 * Localised via tournaments.errors.winner_not_participant (plan 06-01).
 */
final class BracketWinnerNotParticipantException extends DomainException {}
