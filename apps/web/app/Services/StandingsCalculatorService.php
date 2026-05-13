<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-08-PLAN.md Task 1
 *         (stub) — replaced by plan 06-09 (Wave 5 — real standings calc).
 *
 * STUB: this class ships as a no-op so plan 06-08 (BracketAdvancementService)
 * can call `app(StandingsCalculatorService::class)->recalculate($tournament)`
 * without depending on plan 06-09's real implementation.
 *
 * Plan 06-09 will replace this body with the Buchholz-style standings
 * calculator that walks MatchResult rows + writes tournament_standings rows
 * with wins/losses/draws/points/tiebreak_score/rank.
 *
 * Why a stub here (not a contract / interface):
 *   - BracketAdvancementService resolves this via app() (not constructor inject)
 *     specifically to dodge the circular DI cycle with the future
 *     StandingsCalculatorService body (which may itself need to read brackets
 *     written by BracketAdvancementService).
 *   - Shipping a concrete no-op stub means plan 06-09 can replace the body
 *     verbatim without re-routing callers; the public method signature is
 *     locked here.
 *
 * Threat refs:
 *   - T-06-08-07 (circular DI) — mitigated by the app() resolution pattern.
 */
final class StandingsCalculatorService
{
    /**
     * No-op stub. Plan 06-09 replaces with the real Buchholz/round-robin
     * standings writer.
     */
    public function recalculate(Tournament $tournament): void
    {
        // Intentionally empty — plan 06-09 fills the body.
        // $tournament is referenced here to satisfy PHPStan's unused-arg rule.
        unset($tournament);
    }
}
