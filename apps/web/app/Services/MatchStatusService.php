<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Source: 04-04-PLAN.md Task 1 + 04-RESEARCH.md Pattern 4 (Match status state machine).
 *
 * Encodes the Phase 4 match-lifecycle state machine:
 *
 *     draft ──► open ──► locked ──► played   (terminal)
 *       └──► cancelled                  ▲
 *              open ──► played ─────────┘
 *              open/locked ──► cancelled (terminal)
 *
 * Terminal states (`played`, `cancelled`) have NO outgoing transitions.
 *
 * Every successful transition is wrapped in DB::transaction and emits an
 * activity_log row with the causer + properties[from, to] (Spatie activitylog),
 * so the admin audit trail captures status flips even when triggered by
 * side-effect services such as MatchResultService (plan 04-09) which flips
 * status to 'played' atomically inside its result-write transaction.
 *
 * NAMING NOTE (D-04-03-A LOCKED): the Match model is named `App\Models\GameMatch`
 * (class `Match` is a PHP 8.4 parse error — `match` is reserved). Table stays
 * `matches`; FK columns stay `match_id`. This service uses GameMatch directly
 * — no `match($x)` expressions appear here so the alias-on-import pattern from
 * Pitfall 5 is not needed.
 *
 * Threat refs:
 *   - T-04-04-01 (invalid transition via service bypass)        — mitigated here + DB CHECK
 *   - T-04-04-02 (status flip with no audit trail)               — mitigated by activity() write
 *   - T-04-04-03 (Filament admin manual edit of status field)    — mitigated by ->disabled() in plan 04-09
 *   - T-04-04-04 (audit log leaks internal status strings)       — accepted (status is public)
 *
 * Stateless — auto-resolved by the Laravel container.
 */
final class MatchStatusService
{
    /** @var array<string, list<string>> */
    private const ALLOWED_TRANSITIONS = [
        'draft' => ['open', 'cancelled'],
        'open' => ['locked', 'played', 'cancelled'],
        'locked' => ['played', 'cancelled'],
        'played' => [],
        'cancelled' => [],
    ];

    /**
     * Transition $match from its current status to $to.
     *
     * @throws \DomainException When the transition is not allowed by ALLOWED_TRANSITIONS.
     */
    public function transition(GameMatch $match, string $to, User $causer): void
    {
        $from = $match->status;
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new \DomainException(__('matches.status.error.invalid_transition', [
                'from' => $from,
                'to' => $to,
            ]));
        }

        DB::transaction(function () use ($match, $from, $to, $causer): void {
            $match->update(['status' => $to]);

            activity()
                ->causedBy($causer)
                ->performedOn($match)
                ->withProperties(['from' => $from, 'to' => $to])
                ->log('Match status transition');
        });
    }
}
