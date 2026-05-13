<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;
use App\Services\Standings\DoubleEliminationStandingsCalculator;
use App\Services\Standings\RoundRobinStandingsCalculator;
use App\Services\Standings\SingleEliminationStandingsCalculator;
use App\Services\Standings\SwissStandingsCalculator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-09-PLAN.md <interfaces>.
 *
 * Replaces the plan 06-08 no-op stub. Front-door strategy dispatcher for the
 * 4 LOCKED tournament formats (D-011). Wraps the strategy call in
 * DB::transaction + Tournament::lockForUpdate (Pitfall 6 — concurrent
 * BracketAdvancementService advances on the same tournament serialise on the
 * parent row, never racing standings recalc).
 *
 * Wipe-and-recompute strategy: deletes existing tournament_standings rows for
 * the tournament inside the transaction before invoking the strategy. The
 * table is small (≤ 64 rows per tournament) so the cost is negligible
 * compared to upsert-per-participant complexity. If any strategy insert
 * fails, the wipe rolls back atomically — existing standings remain.
 *
 * Public signature `recalculate(Tournament $tournament): void` is LOCKED by
 * plan 06-08. Callers:
 *   - BracketAdvancementService::advance() — via app() lookup (T-06-08-07
 *     circular DI break)
 *   - Filament "Recalculate standings" admin action (plan 06-11)
 *
 * Threat refs:
 *   - T-06-09-01 (concurrent recalculate trample)  — mitigated by lockForUpdate
 *   - T-06-09-04 (partial recalc on failure)       — mitigated by DB::transaction wrap
 */
final class StandingsCalculatorService
{
    public function __construct(
        private readonly SingleEliminationStandingsCalculator $singleElim,
        private readonly DoubleEliminationStandingsCalculator $doubleElim,
        private readonly RoundRobinStandingsCalculator $roundRobin,
        private readonly SwissStandingsCalculator $swiss,
    ) {}

    /**
     * Wipe + recompute standings for $tournament. Idempotent (re-run safely);
     * Pitfall 6 row-lock guarantees serial execution across concurrent
     * callers.
     */
    public function recalculate(Tournament $tournament): void
    {
        $strategy = match ($tournament->format) {
            'single_elimination' => $this->singleElim,
            'double_elimination' => $this->doubleElim,
            'round_robin' => $this->roundRobin,
            'swiss' => $this->swiss,
            default => throw new InvalidArgumentException(
                "Unknown tournament format: {$tournament->format}. Allowed: single_elimination | double_elimination | round_robin | swiss."
            ),
        };

        DB::transaction(function () use ($tournament, $strategy): void {
            // Pitfall 6 — serialise on the parent tournament row.
            Tournament::query()->whereKey($tournament->id)->lockForUpdate()->first();

            // Wipe existing standings (small table, ≤ 64 rows per tournament).
            $tournament->standings()->delete();

            $strategy->compute($tournament);
        });
    }
}
