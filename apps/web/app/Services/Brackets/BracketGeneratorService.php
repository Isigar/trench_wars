<?php

declare(strict_types=1);

namespace App\Services\Brackets;

use App\Exceptions\BracketsAlreadyGeneratedException;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-06-PLAN.md <interfaces>.
 *
 * Front-door for the 4 LOCKED bracket-generator strategies (D-011). PHP 8
 * match() expression dispatches on tournament.format. The 3 non-single-elim
 * generators ship as stubs in plan 06-06 (this plan); plan 06-07 fills the
 * bodies without re-touching this file's constructor signature.
 *
 * Idempotency (Pitfall 3): the generate() method throws
 * BracketsAlreadyGeneratedException when the tournament already has stages.
 * Paired with the Filament admin "Start tournament" action (plan 06-11) which
 * routes through TournamentStatusService::transition($t, 'running') — the
 * status transition is the first line of defence; this exception is the
 * defence-in-depth for non-Filament callers (e.g., console command, queued job).
 *
 * Transaction boundary: generate() wraps the strategy call in DB::transaction
 * so partial stage/bracket writes always roll back atomically. Strategies MUST
 * NOT call DB::transaction themselves — composition is the front-door's job.
 *
 * Threat refs:
 *   - T-06-06-01 (non-idempotency) — mitigated by BracketsAlreadyGeneratedException
 */
final class BracketGeneratorService
{
    public function __construct(
        private readonly SingleEliminationGenerator $singleElim,
        private readonly DoubleEliminationGenerator $doubleElim,
        private readonly RoundRobinGenerator $roundRobin,
        private readonly SwissGenerator $swiss,
    ) {}

    /**
     * Generate the bracket tree for $tournament. Throws if stages already exist.
     *
     * Pre-conditions:
     *   - Tournament has been seeded (participants have non-null `seed` 1..N).
     *   - tournament.status === 'seeded' (caller enforces).
     *
     * Post-conditions:
     *   - tournament_stages rows exist for this tournament (>= 1).
     *   - tournament_brackets rows exist for round 1 with participants assigned.
     *   - Subsequent round brackets exist with advances_to_bracket_id wired.
     *
     * @throws BracketsAlreadyGeneratedException if $tournament->stages()->exists()
     * @throws InvalidArgumentException if the chosen strategy rejects the participant set
     */
    public function generate(Tournament $tournament): void
    {
        if ($tournament->stages()->exists()) {
            throw new BracketsAlreadyGeneratedException(
                (string) __('tournaments.errors.brackets_already_generated')
            );
        }

        /** @var EloquentCollection<int, TournamentParticipant> $participants */
        $participants = $tournament->participants()
            ->where('status', 'active')
            ->orderBy('seed')
            ->get();

        $strategy = match ($tournament->format) {
            'single_elimination' => $this->singleElim,
            'double_elimination' => $this->doubleElim,
            'round_robin' => $this->roundRobin,
            'swiss' => $this->swiss,
            default => throw new InvalidArgumentException(
                "Unknown tournament format: {$tournament->format}. Allowed: single_elimination | double_elimination | round_robin | swiss."
            ),
        };

        DB::transaction(function () use ($strategy, $tournament, $participants): void {
            $strategy->generate($tournament, $participants);
        });
    }
}
