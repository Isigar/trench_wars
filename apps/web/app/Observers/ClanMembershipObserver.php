<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\SyncDiscordRolesJob;
use App\Models\ClanMembership;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Source: .planning/phases/05-discord-bot-v1/05-06-PLAN.md task 2 +
 *         05-RESEARCH.md Pattern 5 (unified outbound delivery).
 *
 * Listens to ClanMembership lifecycle events and dispatches SyncDiscordRolesJob
 * with action=add|remove. SC-4: joining/leaving a clan on the website triggers
 * a Discord role assign/remove via Horizon-retried jobs.
 *
 * Lifecycle → action mapping:
 *   created(left_at IS NULL)         → action=add        (member joined)
 *   created(left_at NOT NULL)        → no dispatch       (historical seed; not an active join)
 *   updated(left_at: null → NOT NULL) → action=remove    (member ended membership)
 *   updated(left_at: NOT NULL → null) → action=add       (rare — re-join scenario)
 *   updated(other field changed)     → no dispatch       (no role-relevant change)
 *   deleted(hard-delete)             → action=remove     (rare — admin cleanup)
 *
 * Pre-flight skip: dispatch is suppressed when user.discord_id is empty OR
 * clan.discord_role_id is empty. Both are populated in normal operation
 * (Phase 1 OAuth ensures discord_id; admin sets discord_role_id via Phase 2
 * plan 02-13 Filament resource); the skip guards against bad data + supports
 * test fixtures that don't seed these values.
 *
 * Registered via ClanMembership::booted() (D-04-08-B precedent — model-level
 * registration fires reliably under test, no AppServiceProvider plumbing).
 *
 * Threat refs:
 *   T-05-06-04 (observer double-fire → duplicate role_sync) — mitigated by
 *     wasChanged('left_at') gate on updated(); only the transitions trigger.
 *   T-05-06-07 (User row with empty discord_id) — mitigated by the binding
 *     pre-flight in dispatchSyncIfBindingComplete().
 */
final class ClanMembershipObserver
{
    /**
     * New ClanMembership row inserted. Treat as a join only if the row is
     * actually active (left_at IS NULL). Historical seeds with left_at set
     * are imported "as-is" and do NOT trigger a Discord role assignment.
     */
    public function created(ClanMembership $membership): void
    {
        if ($membership->left_at !== null) {
            return;
        }

        $this->dispatchSyncIfBindingComplete($membership, 'add');

        // Phase 9 plan 09-05 — flush leaderboards on join (Rule 2
        // additive correctness): a new active membership re-attributes
        // historical kills to the joined clan on the next clan
        // leaderboard read (D-09-05-D current-snapshot semantics).
        Cache::tags(['leaderboards'])->flush();
    }

    /**
     * ClanMembership row updated. Two transitions of left_at trigger a role
     * sync:
     *   null → NOT NULL : member left the clan         → action=remove
     *   NOT NULL → null : member re-joined (rare)       → action=add
     *
     * Any other field change (role, joined_at edit, etc.) is irrelevant to
     * the Discord role binding and produces no dispatch.
     */
    public function updated(ClanMembership $membership): void
    {
        if (! $membership->wasChanged('left_at')) {
            return;
        }

        // Phase 9 plan 09-05 — both transitions of left_at re-attribute
        // historical kill counts in the clan leaderboard (D-09-05-D
        // current-snapshot semantics); flush the leaderboards tag on
        // either edge of the left_at transition.
        Cache::tags(['leaderboards'])->flush();

        if ($membership->left_at !== null) {
            $this->dispatchSyncIfBindingComplete($membership, 'remove');

            return;
        }

        // left_at NOT NULL → null transition (re-join).
        $this->dispatchSyncIfBindingComplete($membership, 'add');
    }

    /**
     * ClanMembership hard-deleted. Rare (the model has no SoftDeletes trait
     * but D-009 history-preservation expects left_at instead of delete);
     * treat as remove for defensive completeness.
     */
    public function deleted(ClanMembership $membership): void
    {
        $this->dispatchSyncIfBindingComplete($membership, 'remove');
    }

    /**
     * Pre-flight: confirm both `user.discord_id` and `clan.discord_role_id`
     * are present before dispatching. The empty-string OR null guard uses
     * the `?? ''` null-coalesce pattern (consistent with codebase style).
     */
    private function dispatchSyncIfBindingComplete(ClanMembership $membership, string $action): void
    {
        $membership->loadMissing(['user', 'clan']);

        // PHPStan infers user/clan as non-null from the BelongsTo<...> generic;
        // the FK columns are NOT NULL in the schema (Phase 2 plan 02-03), so
        // the relations DO resolve in production. The `?? ''` collapses the
        // discord_id / discord_role_id null-or-empty cases under a single check.
        $userDiscordId = $membership->user->discord_id ?? '';
        $roleDiscordId = $membership->clan->discord_role_id ?? '';

        if ($userDiscordId === '' || $roleDiscordId === '') {
            return;
        }

        $causerUserId = Auth::id();
        $causerUserIdString = is_string($causerUserId) ? $causerUserId : null;

        SyncDiscordRolesJob::dispatch($membership->id, $action, $causerUserIdString);
    }
}
