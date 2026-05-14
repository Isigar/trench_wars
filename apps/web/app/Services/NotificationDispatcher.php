<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameMatch;
use App\Models\User;
use App\Notifications\MatchStartingSoon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Source: .planning/phases/09-polish/09-04-PLAN.md task 1 +
 *         .planning/phases/09-polish/09-RESEARCH.md § Pattern 2.
 *
 * Cron-driven sweeper for the SC-1 upcoming-match notification surface. Scans
 * for matches entering the T-60min or T-15min window (±3min slack so a single
 * tick never misses a target on a slow worker) and fires the
 * MatchStartingSoon notification on every signed-up player + every active host
 * clan member.
 *
 * Pitfall 5 LOCKED — read-then-write dedup race:
 *   alreadyDispatched() reads the notifications table for an existing
 *   (type, data->match_id, data->minutes) row before each notify(). The
 *   ->onOneServer() + ->withoutOverlapping() guards in routes/console.php
 *   keep the cron single-fire across Railway multi-replica deploys (D-014).
 *   Inside a single tick, the row written by the first notify() is visible
 *   to alreadyDispatched() for the second iteration, so even the in-flight
 *   sweep is idempotent against the same user.
 *
 * D-04-03-A LOCKED — `App\Models\GameMatch` directly (NOT `App\Models\Match`).
 *
 * Plan-vs-reality drift resolved in this commit (Rule 1):
 *   - Research/plan referenced relations `GameMatch::signups` and
 *     `Clan::activeMemberships`. On-disk those relations do NOT exist —
 *     `GameMatch` exposes `slots()` (HasMany<MatchSlot>) and each slot has
 *     `occupant_user_id`; `Clan` exposes `activeMembers()` (HasMany<ClanMembership>
 *     filtered WHERE left_at IS NULL). The sweep uses the real relations and
 *     dereferences `occupant_user_id` + `user` accordingly. The mid-sweep merged
 *     set is the union of signed-up players + active host-clan members, deduped
 *     by user id, with anonymous/empty slots filtered out.
 *   - Research/plan referenced `status='scheduled'` for matches. The matches
 *     status enum is `draft|open|locked|played|cancelled` (no `scheduled`
 *     status — that's the Article enum). The closest set of "upcoming bookable"
 *     match statuses is `open|locked`. The sweep uses those.
 *
 * Both deviations are documented in the SUMMARY and locked as D-09-04-A.
 */
final class NotificationDispatcher
{
    /**
     * Two fixed windows per RESEARCH Pattern 2 — T-60min and T-15min.
     */
    public function sweepUpcoming(): void
    {
        $this->dispatchWindow(minutes: 60);
        $this->dispatchWindow(minutes: 15);
    }

    /**
     * Scan ±3min around the target window for upcoming-bookable matches and
     * dispatch MatchStartingSoon to every participant that has not already
     * received the notification for this (match, minutes) pair.
     */
    private function dispatchWindow(int $minutes): void
    {
        $target = Carbon::now()->addMinutes($minutes);
        $windowStart = $target->copy()->subMinutes(3);
        $windowEnd = $target->copy()->addMinutes(3);

        GameMatch::query()
            ->whereIn('status', ['open', 'locked'])
            ->where('scheduled_at', '>=', $windowStart)
            ->where('scheduled_at', '<=', $windowEnd)
            ->with([
                'slots.occupantUser',
                'hostClan.activeMembers.user',
            ])
            ->each(function (GameMatch $match) use ($minutes): void {
                $participants = $this->participantsFor($match);

                foreach ($participants as $user) {
                    if ($this->alreadyDispatched($user, $match, $minutes)) {
                        continue;
                    }
                    $user->notify(new MatchStartingSoon($match, $minutes));
                }
            });
    }

    /**
     * Merge signed-up players (match_slots.occupant_user_id non-null) with the
     * host clan's active members (clan_memberships WHERE left_at IS NULL),
     * unique by user id, dropping any null users.
     *
     * @return Collection<int, User>
     */
    private function participantsFor(GameMatch $match): Collection
    {
        $signedUp = $match->slots
            ->pluck('occupantUser')
            ->filter(fn (?User $u): bool => $u !== null);

        $clanMembers = $match->hostClan
            ? $match->hostClan->activeMembers
                ->pluck('user')
                ->filter(fn (?User $u): bool => $u !== null)
            : collect();

        return $signedUp
            ->merge($clanMembers)
            ->unique(fn (User $u): string => $u->id)
            ->values();
    }

    /**
     * Pitfall 5 dedupe key — (notifiable, type, data->match_id, data->minutes).
     *
     * Reads the notifications table directly (DB::table) rather than going
     * through the Notification model so we avoid the polymorphic morph map
     * lookup and stay query-cache friendly. The type discriminator
     * `match.starting_soon` matches MatchStartingSoon::databaseType().
     */
    private function alreadyDispatched(User $user, GameMatch $match, int $minutes): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->where('type', 'match.starting_soon')
            ->whereJsonContains('data->match_id', $match->id)
            ->whereJsonContains('data->minutes', $minutes)
            ->exists();
    }
}
