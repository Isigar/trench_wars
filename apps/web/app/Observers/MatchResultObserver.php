<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DiscordOutboundMessage;
use App\Models\MatchResult;
use App\Models\User;
use App\Notifications\MatchResultPublished;
use App\Services\BracketAdvancementService;
use App\Support\DiscordOutboundPayloadBuilder;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-08-PLAN.md Task 2 +
 *         06-RESEARCH.md § Pattern 7 (Option A — observer over inline call).
 *
 * Fires BracketAdvancementService::advance() on every relevant MatchResult save.
 * The service itself short-circuits for non-tournament matches (no bracket
 * links to the result's match_id), so this observer can fire unconditionally
 * for tournament-match results AND non-tournament-match results — the cost
 * is one extra SELECT against tournament_brackets per save.
 *
 * NAMING (D-04-03-A): MatchResult is the canonical class name (it's a
 * separate model from GameMatch — GameMatch holds the schedule + status;
 * MatchResult holds the final score + winner_clan_id + recorded_at).
 *
 * Threat refs:
 *   - T-06-08-01 (concurrent advance() race) — mitigated inside the service
 *     by DB::transaction + Tournament::lockForUpdate.
 *   - Pitfall 12 caveat: bulk updates (`MatchResult::query()->update(...)`)
 *     bypass model events and therefore this observer. Filament's EditAction
 *     uses `$model->save()` which fires the appropriate created/updated
 *     event. Do not add bulk score-edit actions; iterate models if a batch
 *     operation is needed.
 *
 * Two-hook pattern — `created()` + `updated()` instead of `saved()`:
 *   - `saved()` fires for both inserts AND plain `touch()` calls. On the
 *     Laravel version pinned here, fresh inserts emit getChanges()=[] AND
 *     touch() emits getChanges()=[] AND wasRecentlyCreated stays true on
 *     the same instance forever. There is no reliable flag combination on
 *     `saved` that distinguishes a fresh insert from a touch on a
 *     previously-recently-created instance.
 *   - Eloquent fires `created` only on the actual insert and `updated` only
 *     when at least one attribute was dirty at save time. Plain touch()
 *     (no dirty attributes) emits neither `created` nor `updated` —
 *     exactly the gate we need.
 *   - `updated()` additionally re-checks wasChanged() for the relevant
 *     attribute set so unrelated edits (e.g., recorded_by_user_id swap)
 *     do not re-fire advance().
 *
 * Side-effect: Filament inline editing of `notes` or `recorded_by_user_id`
 * does NOT re-trigger advance() — only changes to score/winner/recorded_at
 * cause re-propagation. This matches the user's expectation that fixing a
 * typo in the notes field should not reset the bracket tree.
 */
class MatchResultObserver
{
    /**
     * Fresh-insert hook. Fires once for newly-created MatchResult rows.
     *
     * Eloquent guarantees `created` does NOT fire for plain timestamp-only
     * touch() calls (touch() updates updated_at on an existing row → fires
     * `saved` only, NOT `created`/`updated`). Using the dedicated `created`
     * hook means no wasRecentlyCreated/getChanges disambiguation is needed
     * for the insert path.
     */
    public function created(MatchResult $result): void
    {
        // Phase 6 BracketAdvancement — only fires when a winner has been
        // recorded. Round-1 RCON-sourced results land with winner_clan_id=null
        // (CRCON cannot map allies/axis labels to clan IDs deterministically);
        // the bracket advance is a no-op in that case.
        if ($result->winner_clan_id !== null) {
            app(BracketAdvancementService::class)->advance($result);
        }

        // Phase 8 plan 08-12 — RCON-sourced result announce.
        $this->maybeAnnounceRconResult($result);

        // Phase 9 plan 09-04 — additive notification dispatch.
        // Plan 09-05 will add the leaderboards cache flush in the same hook.
        $this->notifyResultPublished($result);
    }

    /**
     * Update hook. Fires only when at least one dirty attribute was persisted
     * (Eloquent does NOT fire `updated` for touch() with no dirty fields).
     *
     * Even so, we gate on `wasChanged()` for the relevant attributes so an
     * unrelated edit (e.g., recorded_by_user_id reassignment) does not trigger
     * a redundant advance() — the bracket tree already settled when the score
     * was first recorded.
     */
    public function updated(MatchResult $result): void
    {
        // Phase 6 BracketAdvancement gated on a relevant attribute change AND
        // a non-null winner_clan_id (round-1 RCON results never advance the
        // bracket — they're scrims, not tournament matches).
        if ($result->winner_clan_id !== null) {
            $relevantChange = $result->wasChanged([
                'winner_clan_id',
                'allies_score',
                'axis_score',
                'recorded_at',
            ]);

            if ($relevantChange) {
                app(BracketAdvancementService::class)->advance($result);
            }
        }

        // Phase 8 plan 08-12 — re-runs of upsertFromRcon hit the `updated`
        // path; the `alreadyAnnounced` guard inside maybeAnnounceRconResult
        // keeps the outbox row count idempotent (must_haves.truths #2 +
        // T-08-12-02 mitigation — RconBotResultAnnounceTest case 4).
        $this->maybeAnnounceRconResult($result);
    }

    /**
     * Phase 8 plan 08-12 — outbound match-result announce branch.
     *
     * Fires when:
     *   - $result->source === 'rcon'                  (D-019 — only auto-recorded results announce)
     *   - $result->match->host_clan_id !== null       (we need a host clan to resolve the channel)
     *   - $result->match->hostClan->discord_announce_channel_id is non-null
     *   - No prior `match_result_announce` row exists for this match (idempotency)
     *
     * The destination channel resolution mirrors the Phase 5 outbox pattern: the
     * outbox `channel_id` column carries the resolved Discord snowflake; the bot
     * worker posts to whatever channel the row names. We resolve here (not in the
     * builder) because the builder is shape-only — the outbox row stores the
     * routing decision so a channel-id rename mid-flight still emits the correct
     * snowflake-of-record at the time the result landed.
     *
     * Threat refs:
     *   - T-08-12-01 — payload uses `display_name` only (never Steam ID); enforced
     *     in DiscordOutboundPayloadBuilder::buildMatchResultAnnounce.
     *   - T-08-12-02 — alreadyAnnounced check makes the observer re-fire safe.
     */
    private function maybeAnnounceRconResult(MatchResult $result): void
    {
        if ($result->source !== 'rcon') {
            return;
        }

        $result->loadMissing(['match.hostClan']);

        $match = $result->match;
        if ($match === null || $match->host_clan_id === null) {
            return;
        }

        $channelId = $match->hostClan?->discord_announce_channel_id;
        if ($channelId === null || $channelId === '') {
            return;
        }

        $alreadyAnnounced = DiscordOutboundMessage::query()
            ->where('message_type', 'match_result_announce')
            ->whereJsonContains('payload->match_id', $result->match_id)
            ->exists();

        if ($alreadyAnnounced) {
            return;
        }

        $payload = DiscordOutboundPayloadBuilder::buildMatchResultAnnounce($result);

        DiscordOutboundMessage::create([
            'channel_id' => $channelId,
            'message_type' => 'match_result_announce',
            'status' => 'pending',
            'payload' => $payload,
            'causer_user_id' => null,
        ]);
    }

    /**
     * Phase 9 plan 09-04 — fire MatchResultPublished to every signed-up
     * player + every active host-clan member when the result is created
     * for the first time.
     *
     * Fires from the `created()` hook ONLY (per plan: "on first MatchResult
     * create"). Updates to the result (score edits, winner reassignment) do
     * NOT re-fire — the user has already been told the result was published
     * and the result-edit audit lives in activity_log.
     *
     * The Notification class delegates `via()` to
     * User::enabledNotificationChannels('match_result_published') — which
     * per Open Question 3 LOCKED is database-only by default; Discord DMs
     * require an explicit opt-in row in user_notification_preferences. This
     * is intentional: the dispatch path is uniform, the policy is one layer
     * up in the Notifiable matrix.
     *
     * Recipient set: signed-up players + active host-clan members merged
     * unique by user id. Plan also mentioned guest-clan active members, but
     * the GameMatch schema has no `away_clan_id` / `guest_clan_id` column on
     * the matches table — the v1 GameMatch is host-clan only (see plan 04
     * + GameMatch model docblock). Adding guest-clan recipients would be a
     * schema change and is deferred to a future phase.
     */
    private function notifyResultPublished(MatchResult $result): void
    {
        $result->loadMissing([
            'match.slots.occupantUser',
            'match.hostClan.activeMembers.user',
        ]);

        $match = $result->match;
        if ($match === null) {
            return;
        }

        $signedUp = $match->slots
            ->pluck('occupantUser')
            ->filter(fn (?User $u): bool => $u !== null);

        $clanMembers = $match->hostClan
            ? $match->hostClan->activeMembers
                ->pluck('user')
                ->filter(fn (?User $u): bool => $u !== null)
            : collect();

        $recipients = $signedUp
            ->merge($clanMembers)
            ->unique(fn (User $u): string => $u->id)
            ->values();

        foreach ($recipients as $user) {
            $user->notify(new MatchResultPublished($match, $result->winnerClan));
        }
    }
}
