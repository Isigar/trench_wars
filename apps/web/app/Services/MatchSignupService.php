<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AlreadySignedUpException;
use App\Exceptions\CapacityExceededException;
use App\Exceptions\MatchNotOpenException;
use App\Exceptions\TagRestrictedException;
use App\Models\GameMatch;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Source: 04-06-PLAN.md Task 1 + 04-RESEARCH.md Pattern 2 (D-010 row-locked
 * transactional capacity service) + Pattern 5 (tag-access allowlist).
 *
 * THE single production write path to `match_slots.occupant_user_id`. Every
 * caller — controller (plan 04-10), Discord bot via Sanctum (Phase 5), any
 * future automation — funnels through this service. Direct writes from
 * elsewhere are forbidden by convention and audited by LogsActivity (plan
 * 04-03).
 *
 * The five guards execute IN ORDER, all INSIDE a single DB::transaction
 * with a Match-row exclusive lock acquired by `lockForUpdate()->findOrFail()`:
 *
 *   1. status === 'open'                 → else MatchNotOpenException
 *   2. tag access allowlist intersection → else TagRestrictedException
 *   3. one-slot-per-user-per-match       → else AlreadySignedUpException
 *   4. occupied < total capacity         → else CapacityExceededException
 *   5. claim lowest-index empty slot     → atomically writes occupant_user_id + confirmed_at
 *
 * The lock is acquired on the PARENT GameMatch row (not on individual slots)
 * so two simultaneous signups for the same match serialize on the same row.
 * The COUNT-then-UPDATE between guards 4 and 5 is atomic because the parent
 * lock blocks every concurrent writer for the same $match->id until this
 * transaction commits or rolls back (Pattern 2 row-lock pattern; Pitfall 1
 * defense — `lockForUpdate` OUTSIDE a transaction is a no-op).
 *
 * Tag-access allowlist (Pattern 5):
 *   - Zero match_access_rules rows           → open to all (return true)
 *   - >=1 rows + user has no active clan     → blocked (return false)
 *   - >=1 rows + user clan tags intersect    → allowed
 *   - >=1 rows + user clan tags do not       → blocked
 *
 * NAMING NOTE (D-04-03-A LOCKED): the Match model is `App\Models\GameMatch`
 * (class `Match` is a PHP 8.4 parse error — `match` is reserved). Table
 * stays `matches`; FK columns stay `match_id`. This service uses GameMatch
 * directly — no `match($x)` expressions appear here, so the Pitfall 5
 * alias-on-import is not needed (D-04-04-C / D-04-05-B canonical idiom).
 *
 * Threat refs:
 *   - T-04-06-01 (Tampering — CRITICAL SC-2 capacity bypass) — mitigated
 *     by lockForUpdate inside DB::transaction; proven by
 *     MatchSignupConcurrencyTest (pcntl_fork parallel-process race).
 *   - T-04-06-02 (Elevation of Privilege — tag-access bypass) — mitigated
 *     by tagAccessAllowed() server-side check.
 *   - T-04-06-03 (Spoofing — IDOR signing up another user) — mitigated by
 *     typed User parameter; controller passes auth()->user() only.
 *   - T-04-06-04 (Tampering — mass-assignment) — mitigated by service-only
 *     write path + LogsActivity audit.
 *   - T-04-06-05 (Tampering — status race) — mitigated by reading
 *     $locked->status (the freshly-locked row) inside the transaction.
 *   - T-04-06-08 (lockForUpdate outside transaction — Pitfall 1) —
 *     mitigated structurally: the only lockForUpdate call site IS the
 *     line below, and it lives inside DB::transaction.
 *
 * Stateless — auto-resolved by the Laravel container.
 */
final class MatchSignupService
{
    /**
     * Sign $user up to the named $gameRole on $match, claiming the lowest-
     * indexed empty slot atomically inside a row-locked transaction.
     *
     * @throws MatchNotOpenException When $match->status !== 'open'.
     * @throws TagRestrictedException When match has access rules and user's
     *                                active clan does not carry an allowed tag.
     * @throws AlreadySignedUpException When the user already occupies a slot in
     *                                  this match (any role).
     * @throws CapacityExceededException When all slots for $gameRole on $match
     *                                   are already occupied.
     */
    public function signup(GameMatch $match, User $user, GameRole $gameRole): MatchSlot
    {
        /** @var MatchSlot $emptySlot */
        $emptySlot = DB::transaction(function () use ($match, $user, $gameRole): MatchSlot {
            // 1. Acquire row-level exclusive lock on the parent Match row.
            //    findOrFail re-reads the row WITH the lock — $match (passed in
            //    via route model binding) is the unlocked stale copy; $locked
            //    is the freshly-locked authoritative row.
            $locked = GameMatch::lockForUpdate()->findOrFail($match->id);

            // 2. Status guard (T-04-06-05 — read $locked->status, not $match->status).
            if ($locked->status !== 'open') {
                throw new MatchNotOpenException(__('matches.signup.error.not_open'));
            }

            // 3. Tag access allowlist (Pattern 5; SC-5; T-04-06-02).
            if (! $this->tagAccessAllowed($user, $locked)) {
                throw new TagRestrictedException(__('matches.signup.error.tag_restricted'));
            }

            // 4. Idempotency — one slot per user per match (any role).
            $existing = MatchSlot::where('match_id', $locked->id)
                ->where('occupant_user_id', $user->id)
                ->first();
            if ($existing !== null) {
                throw new AlreadySignedUpException(__('matches.signup.error.already_signed_up'));
            }

            // 5. Capacity check — COUNT(occupied) vs COUNT(total) for the (match, role)
            //    pair. The parent lock makes COUNT-then-UPDATE atomic against concurrent
            //    writers for the same match.
            $occupiedCount = MatchSlot::where('match_id', $locked->id)
                ->where('game_role_id', $gameRole->id)
                ->whereNotNull('occupant_user_id')
                ->count();
            $totalCapacity = MatchSlot::where('match_id', $locked->id)
                ->where('game_role_id', $gameRole->id)
                ->count();
            if ($occupiedCount >= $totalCapacity) {
                throw new CapacityExceededException(__('matches.signup.error.capacity_full'));
            }

            // 6. Claim the lowest-index empty slot (deterministic — Pattern 2).
            //    firstOrFail is defensive: because $occupiedCount < $totalCapacity
            //    inside the locked transaction, an empty slot MUST exist; the
            //    exception path is unreachable in practice.
            $slot = MatchSlot::where('match_id', $locked->id)
                ->where('game_role_id', $gameRole->id)
                ->whereNull('occupant_user_id')
                ->orderBy('slot_index')
                ->firstOrFail();
            $slot->update([
                'occupant_user_id' => $user->id,
                'confirmed_at' => now(),
            ]);

            return $slot;
        });

        return $emptySlot;
    }

    /**
     * Pattern 5 tag-access allowlist check.
     *
     * Empty match_access_rules = open to all (returns true). Non-empty rules
     * require the user's active ClanMembership.clan to carry at least one of
     * the allowlisted clan_tag_ids. Users with no active clan are blocked
     * when rules exist.
     */
    private function tagAccessAllowed(User $user, GameMatch $match): bool
    {
        if ($match->accessRules()->count() === 0) {
            return true;
        }

        $userClan = $user->activeClanMembership?->clan;
        if ($userClan === null) {
            return false;
        }

        $userTagIds = $userClan->tags()->pluck('clan_tags.id');
        $allowedTagIds = $match->accessRules()->pluck('clan_tag_id');

        return $userTagIds->intersect($allowedTagIds)->isNotEmpty();
    }
}
