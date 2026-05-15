<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Source: .planning/phases/09-polish/09-07-PLAN.md task 1 +
 *         09-RESEARCH.md § Moderator Tooling — Dispute state machine.
 *
 * Thrown by `DisputeService::transition()` when a moderator attempts a state
 * transition not in the allow-list:
 *
 *   open          -> under_review
 *   under_review  -> resolved | rejected
 *   rejected      -> under_review              (re-open after rejection)
 *
 * Symmetric to Phase 7's `InvalidArticleStatusTransitionException` and
 * Phase 6's `TournamentStatusInvalidTransitionException` (D-06-04-A
 * precedent): the from/to pair is captured on the exception for both
 * `getMessage()` rendering and downstream notification copy.
 *
 * Mitigates T-09-07-07 (Tampering — invalid dispute transition bypassing
 * state machine).
 */
final class InvalidDisputeTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct(sprintf(
            'Invalid match-dispute transition: %s -> %s.',
            $from,
            $to,
        ));
    }
}
