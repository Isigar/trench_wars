<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TournamentStatusInvalidTransitionException;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Source: 06-04-PLAN.md Task 1 + 06-RESEARCH.md Pattern 1 (Tournament status state machine).
 *
 * Encodes the Phase 6 tournament-lifecycle state machine:
 *
 *     draft ──► registering ──► seeded ──► running ──► completed   (terminal)
 *       │           │             │           │
 *       │           │             │           └────► cancelled     (terminal)
 *       │           │             ├────► cancelled
 *       │           │             └────► registering (reseed back-transition)
 *       │           └────► cancelled
 *       └────► cancelled
 *
 * Terminal states (`completed`, `cancelled`) have NO outgoing transitions.
 * The `seeded → registering` back-transition is the canonical reseed path consumed
 * by plan 06-05's TournamentSeedingService::reseed(); the model-level canReseed()
 * method gates the action's visibility in Filament (plan 06-11).
 *
 * Every successful transition is wrapped in DB::transaction and emits an
 * activity_log row with the causer + properties[from, to] (Spatie activitylog),
 * so the admin audit trail captures status flips even when triggered by side-effect
 * services. Verbatim mirror of Phase 4 MatchStatusService (D-04-04-A).
 *
 * The 2 sibling exception classes (TournamentStatusInvalidTransitionException,
 * BracketsAlreadyGeneratedException) ship in the same plan to break the circular
 * dependency with plan 06-06's BracketGeneratorService.
 *
 * Threat refs:
 *   - T-06-04-01 (invalid transition via service bypass)            — mitigated here + DB CHECK
 *   - T-06-04-02 (status flip with no audit trail)                  — mitigated by activity() write
 *   - T-06-04-03 (Filament admin manual edit of status field)       — mitigated by ->disabled() in plan 06-11
 *   - T-06-04-04 (audit log leaks internal status strings)          — accepted (status is public)
 *   - T-06-04-05 (caller spoofs causer)                             — accepted (upstream concern)
 *
 * Stateless — auto-resolved by the Laravel container.
 */
final class TournamentStatusService
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'draft' => ['registering', 'cancelled'],
        'registering' => ['seeded', 'cancelled'],
        'seeded' => ['running', 'registering', 'cancelled'],
        'running' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * Transition $tournament from its current status to $to.
     *
     * @throws TournamentStatusInvalidTransitionException When the (from, to) pair is not in ALLOWED.
     */
    public function transition(Tournament $tournament, string $to, ?User $causer = null): Tournament
    {
        $from = $tournament->status;
        $allowed = self::ALLOWED[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new TournamentStatusInvalidTransitionException(__('tournaments.errors.invalid_transition', [
                'from' => $from,
                'to' => $to,
            ]));
        }

        return DB::transaction(function () use ($tournament, $from, $to, $causer): Tournament {
            $tournament->update(['status' => $to]);

            activity()
                ->causedBy($causer ?? auth()->user())
                ->performedOn($tournament)
                ->withProperties(['from' => $from, 'to' => $to])
                ->log("Tournament status: {$from} -> {$to}");

            return $tournament;
        });
    }
}
