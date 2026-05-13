<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ClanMembership;
use App\Models\DiscordOutboundMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Source: .planning/phases/05-discord-bot-v1/05-06-PLAN.md task 1 +
 *         05-RESEARCH.md Pattern 5 (unified outbound delivery) +
 *         §Standard Stack Horizon verification ($tries + backoff arrays).
 *
 * SC-4 — joining/leaving a clan on the website triggers a Discord role assign/
 * remove via a Horizon-retried job. The job does NOT call Discord directly; it
 * writes a `discord_outbound_messages` row of `message_type='role_sync'` (per
 * RESEARCH Pattern 5 unified outbound table). Plan 05-11's bot worker (the
 * outbound poller) consumes the row and calls Discord's REST
 * `PUT/DELETE /guilds/{g}/members/{u}/roles/{r}` endpoint. Two-layer retry:
 *  - Horizon's $tries/backoff retries the JOB if handle() itself crashes
 *    (DB unavailable, etc.).
 *  - The outbound table's `backoff_until` column retries DELIVERY if Discord
 *    429s or 5xxes (plan 05-04 + 05-11 territory).
 *
 * Dispatched from `App\Observers\ClanMembershipObserver` on
 * ClanMembership::created / updated(left_at transitioned) / deleted. The job
 * carries `(string $membershipId, string $action, ?string $causerUserId)` —
 * primitives only, NEVER an Eloquent model instance. Serialising a model into
 * a queue payload can break if the row is deleted between dispatch and handle
 * (canonical Laravel queue idiom — use ids).
 *
 * Payload key naming: `discord_user_id` / `discord_role_id` — matches the
 * shape the existing `BotApiDiscordEventController::roleChange` queries for
 * echo suppression (Pitfall 10 — `payload->discord_user_id` JSONB path) and
 * the `DiscordOutboundMessageFactory::roleSync()` state already shipped in
 * plan 05-02. The plan 05-06 `<interfaces>` block used `user_discord_id` /
 * `role_discord_id` (verbatim) — this implementation deviates to keep the
 * keys consistent across the codebase. Echo suppression breaks otherwise.
 *
 * Threat refs:
 *   T-05-06-04 (observer double-fire → duplicate role_sync) — mitigated by
 *     wasChanged('left_at') gate on observer.updated() AND Discord's PUT/DELETE
 *     idempotency (Pattern 5 — 204 either way).
 *   T-05-06-06 (Horizon retries handle() after partial write → duplicate row) —
 *     accepted; duplicate role_sync rows are harmless because the bot's PUT is
 *     idempotent (Discord returns 204 whether role was added or already
 *     present).
 *   T-05-06-07 (User row exists with empty discord_id) — defensive double-check
 *     here AND in the observer; both skip dispatch.
 */
final class SyncDiscordRolesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Horizon-canonical retry count (RESEARCH §Standard Stack verification).
     */
    public int $tries = 5;

    /**
     * @param  string  $membershipId  The ClanMembership::id (UUID) to sync — primitive, not the model.
     * @param  string  $action  Either 'add' or 'remove' — drives Discord PUT vs DELETE on the worker side.
     * @param  string|null  $causerUserId  Optional User::id (UUID) of the human/bot that triggered the membership change; null for CLI/seeder flows.
     */
    public function __construct(
        public readonly string $membershipId,
        public readonly string $action,
        public readonly ?string $causerUserId = null,
    ) {}

    /**
     * Exponential backoff schedule (seconds) for Horizon retries.
     *
     * Matches the outbound table's delivery backoff schedule used by plan 05-04
     * BotApiOutboundController — same shape, different layer. Total worst-case
     * delay across 5 tries: 1 + 5 + 15 + 60 + 300 = 381s (~6.5min).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 5, 15, 60, 300];
    }

    /**
     * Re-hydrate the membership, verify the binding is still complete, and
     * write a single pending `role_sync` outbound row for the bot worker
     * (plan 05-11) to deliver.
     *
     * Returns early without writing in three defensive cases:
     *  1. Membership was hard-deleted between dispatch and handle (find()
     *     returns null) — nothing to sync.
     *  2. user.discord_id is empty/null — User row exists but has never
     *     completed Discord OAuth. Unreachable in production after Phase 1
     *     OAuth flow; defensive (T-05-06-07).
     *  3. clan.discord_role_id is empty/null — admin hasn't bound the clan
     *     to a Discord role via Filament (Phase 2 plan 02-13). Defensive —
     *     observer should have caught this.
     */
    public function handle(): void
    {
        $membership = ClanMembership::query()
            ->with(['user', 'clan'])
            ->find($this->membershipId);

        if ($membership === null) {
            return;
        }

        $userDiscordId = $membership->user?->discord_id;
        $roleDiscordId = $membership->clan?->discord_role_id;

        if ($userDiscordId === null || $userDiscordId === '' || $roleDiscordId === null || $roleDiscordId === '') {
            return;
        }

        DiscordOutboundMessage::create([
            // role_sync uses the Guilds API (PUT/DELETE /guilds/{g}/members/{u}/roles/{r}),
            // not a channel POST — the channel_id column is text NOT NULL per plan 05-02
            // migration, so pass empty string to satisfy the constraint. The bot worker
            // keys off message_type='role_sync' and reads the payload below.
            'channel_id' => '',
            'message_type' => 'role_sync',
            'status' => 'pending',
            'payload' => [
                'discord_user_id' => $userDiscordId,
                'discord_role_id' => $roleDiscordId,
                'action' => $this->action,
                'membership_id' => $membership->id,
                'clan_id' => $membership->clan_id,
                'user_id' => $membership->user_id,
            ],
            'causer_user_id' => $this->causerUserId,
        ]);
    }
}
