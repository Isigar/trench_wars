<?php

declare(strict_types=1);

namespace App\Services\Standings;

use App\Models\Tournament;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-09-PLAN.md <interfaces>.
 *
 * Strategy contract for format-specific tournament standings calculators.
 *
 * compute() writes tournament_standings rows for a single tournament. It is
 * always invoked from inside a DB::transaction by StandingsCalculatorService,
 * which also wipes existing standings on the same transaction first — so each
 * strategy can assume an empty target table for the tournament.
 *
 * Strategies MUST NOT call DB::transaction themselves (caller owns the
 * boundary) and MUST be idempotent on re-call with the same tournament.
 *
 * Mirrors the BracketGeneratorStrategy / BracketGeneratorService pattern from
 * plan 06-06 / 06-07.
 */
interface StandingsCalculatorStrategy
{
    public function compute(Tournament $tournament): void;
}
