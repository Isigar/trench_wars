<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Source: .planning/phases/09-polish/09-07-PLAN.md task 1 +
 *         09-02 migration partial-unique index `one_open_dispute_per_user_per_match` (Pitfall 11).
 *
 * Thrown by `DisputeService::open()` when a user attempts to open a second
 * open dispute on a match for which they already have one in flight.
 * Maps directly from Postgres unique-violation 23505 on the partial UNIQUE
 * index `(match_id, raised_by_user_id) WHERE status='open'`. The plan-09-07
 * service catches the QueryException, sniffs SQLSTATE 23505 + the index name,
 * and re-throws as this domain exception so callers (Filament Actions, the
 * future public dispute form) can present a tidy 422 instead of a SQL stack
 * trace.
 *
 * Mirrors the Phase 2 `AlreadySignedUpException` idiom (D-04-06-A): SQLSTATE
 * 23505 is the canonical "uniqueness violated" signal in Postgres; index name
 * disambiguates which unique constraint fired (a match can technically have
 * many unique constraints — partial UNIQUEs included).
 */
final class DisputeAlreadyOpenException extends RuntimeException
{
    public function __construct(
        public readonly string $matchId,
        public readonly string $raisedByUserId,
    ) {
        parent::__construct(sprintf(
            'User %s already has an open dispute on match %s.',
            $raisedByUserId,
            $matchId,
        ));
    }
}
