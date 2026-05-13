<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Thrown by App\Services\Brackets\SwissGenerator::generate()
 * when participants_count < 2^ceil(log2(N)) (RESEARCH Pitfall 5).
 *
 * E.g., 3 participants × ceil(log2(3))=2 rounds requires 2^2=4 minimum → throw.
 * 4 participants × 2 rounds requires 4 → pass.
 *
 * Source: 06-07-PLAN.md Task 3 + RESEARCH Pitfall 5.
 *
 * Threat ref: T-06-07-01 (swiss never-paired-before backtrack loops infinitely
 * for too-small participant counts) — mitigated by throwing this exception at
 * generate() entry before any pairing work is attempted.
 */
final class SwissTooFewParticipantsException extends DomainException {}
