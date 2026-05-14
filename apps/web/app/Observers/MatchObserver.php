<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DiscordOutboundMessage;
use App\Models\Event;
use App\Models\GameMatch;
use App\Models\User;
use App\Notifications\MatchCancelled;
use App\Support\DiscordOutboundPayloadBuilder;

/**
 * Source: .planning/phases/04-matches-manual/04-08-PLAN.md Task 1 +
 *         04-RESEARCH.md § Pattern 8 (polymorphic Event sync) +
 *         05-05-PLAN.md (Wave 4 — Discord outbound writer extension).
 *
 * Two side-effects per match save:
 *   1. (Phase 4) Keep the polymorphic `events` row coherent with `matches.is_public`
 *      and `matches.status` for the unified `/events` calendar — saved() hook.
 *   2. (Phase 5 plan 05-05) Queue a pending `discord_outbound_messages` row whenever
 *      a public match (with a configured host-clan announce channel) is created
 *      or transitions status — created() + updated() hooks. The bot worker
 *      (plan 05-11) picks the row up via the Pattern 4 atomic claim and renders
 *      an embed + RSVP buttons.
 *
 * NAMING (D-04-03-A LOCKED + D-04-07-C): the owner model is `App\Models\GameMatch`,
 * NOT `App\Models\Match` — `match` is a reserved PHP 8 keyword. Pattern 8 in
 * 04-RESEARCH.md predates the rename and aliases `App\Models\Match as MatchModel`;
 * Phase 4 implementations use `GameMatch` directly (matches Match*Service idiom).
 *
 * Threat refs:
 *   - T-04-08-01 Cancelled match retains Event row → saved() deletes when status=cancelled
 *   - T-04-08-02 events_one_per_owner UNIQUE violation → updateOrCreate is idempotent
 *   - T-04-08-03 events.title cache drift from Match.title edit → saved() overwrites every save
 *   - T-05-05-01 Private match leaks via outbound row → is_public guard at top of
 *     writeMatchAnnounceIfEligible (private match → no outbound row written)
 *   - T-05-05-04 Mass UPDATE on matches triggers thousands of outbound rows →
 *     updated() gates on wasChanged('status'); title-only edits do NOT fire outbound
 *   - T-05-05-06 Double-fire on the same match → model-level booted() registration
 *     (D-04-08-B) ensures observer fires once per save
 *
 * Pitfall 12 caveat: bulk updates (`GameMatch::query()->update(...)`) bypass model events
 * and therefore this observer. Filament's standard EditAction uses `$model->save()` which
 * fires the saved event correctly. Do not add bulk publish/cancel actions; iterate models.
 */
class MatchObserver
{
    /**
     * Upsert/delete the Event row to mirror the match's public+non-cancelled state.
     *
     * Runs inside the same DB::transaction as the model save (Eloquent default), so an
     * outer rollback discards this write too.
     */
    public function saved(GameMatch $match): void
    {
        $shouldHaveEvent = $match->is_public && $match->status !== 'cancelled';

        if ($shouldHaveEvent) {
            Event::updateOrCreate(
                ['eventable_type' => GameMatch::class, 'eventable_id' => $match->id],
                [
                    'starts_at' => $match->scheduled_at,
                    'ends_at' => null,
                    'title' => $match->getTranslations('title'),
                    'is_public' => $match->is_public,
                ],
            );

            return;
        }

        Event::where('eventable_type', GameMatch::class)
            ->where('eventable_id', $match->id)
            ->delete();
    }

    /**
     * Phase 5 plan 05-05 addition — write a `match_announce` outbound row for
     * each newly created public match whose host clan has a configured
     * announce channel.
     *
     * Fires AFTER saved() (Eloquent order: creating → created → saving → saved
     * → ... wait — actually saving + saved bracket created, and ALL of them fire
     * for an insert). Order is: saving, creating, [persist], created, saved.
     * The Event row is therefore already present (or absent) by the time this
     * runs. Outbound write is additive — no coupling to the Event row.
     */
    public function created(GameMatch $match): void
    {
        $this->writeMatchAnnounceIfEligible($match, isUpdate: false, priorSentMessageId: null);
    }

    /**
     * Phase 5 plan 05-05 addition — write a `match_announce` outbound row
     * (with the prior sent_message_id, if any) when a match's status
     * transitions. The bot worker (plan 05-11) uses prior_sent_message_id to
     * EDIT the original Discord message rather than POST a new one (T-05-05-04
     * mitigation — no channel spam on status flips).
     *
     * Gated on wasChanged('status') — title/description/etc. edits do NOT
     * trigger outbound rows. The Phase 4 saved() hook above still keeps the
     * Event row coherent on every save.
     */
    public function updated(GameMatch $match): void
    {
        if (! $match->wasChanged('status')) {
            return;
        }

        $prior = DiscordOutboundMessage::query()
            ->where('message_type', 'match_announce')
            ->where('status', 'sent')
            ->where('payload->match_id', $match->id)
            ->orderByDesc('updated_at')
            ->first();

        $this->writeMatchAnnounceIfEligible(
            $match,
            isUpdate: true,
            priorSentMessageId: $prior?->sent_message_id,
        );

        $this->maybeNotifyCancellation($match);
    }

    /**
     * Phase 9 plan 09-04 addition — fire MatchCancelled to every signed-up
     * player + every active host-clan member when a match transitions INTO
     * the `cancelled` status from any prior state.
     *
     * Plan asked for `scheduled→cancelled` specifically; the matches.status
     * enum has no `scheduled` value (it's `draft|open|locked|played|cancelled`).
     * The semantically correct trigger is "transitioning into cancelled from
     * a non-cancelled state" — which matches the user-facing intent (notify
     * participants when their booked match is called off, regardless of which
     * draft-vs-open-vs-locked state it was in when the organiser hit cancel).
     *
     * Recipients are merged unique by user id; null users are filtered. The
     * loop fires `$user->notify(new MatchCancelled(...))` per recipient — each
     * `via()` call resolves the user's enabledNotificationChannels matrix
     * (Pattern 7 of 09-RESEARCH.md, locked in plan 09-03).
     *
     * Eager-loads `slots.occupantUser` + `hostClan.activeMembers.user` before
     * the iteration so plan 09-08's strict-mode flip does not trip an N+1.
     */
    private function maybeNotifyCancellation(GameMatch $match): void
    {
        if ($match->status !== 'cancelled') {
            return;
        }
        if ($match->getOriginal('status') === 'cancelled') {
            // Same status (cancelled→cancelled) — already announced.
            return;
        }

        $match->loadMissing([
            'slots.occupantUser',
            'hostClan.activeMembers.user',
        ]);

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
            $user->notify(new MatchCancelled($match));
        }
    }

    /**
     * Hard-delete cascade: events table has no FK on the polymorphic owner, so the
     * observer is the only cleanup path. RefreshDatabase resets between tests.
     */
    public function deleted(GameMatch $match): void
    {
        Event::where('eventable_type', GameMatch::class)
            ->where('eventable_id', $match->id)
            ->delete();
    }

    /**
     * Single eligibility gate + outbound writer shared by created() + updated().
     *
     * Two guards:
     *   - $match->is_public must be true (T-05-05-01)
     *   - $match->hostClan->discord_announce_channel_id must be non-null
     *
     * `causer_user_id` is auth()->id() so Filament admin edits attribute the
     * outbound row to the human; CLI seeders / system flows write null
     * (T-05-05-03 accept).
     */
    private function writeMatchAnnounceIfEligible(
        GameMatch $match,
        bool $isUpdate,
        ?string $priorSentMessageId,
    ): void {
        if (! $match->is_public) {
            return;
        }

        $channelId = $match->hostClan?->discord_announce_channel_id;
        if ($channelId === null) {
            return;
        }

        $payload = $isUpdate
            ? DiscordOutboundPayloadBuilder::buildMatchUpdate($match, $priorSentMessageId)
            : DiscordOutboundPayloadBuilder::buildMatchAnnounce($match);

        DiscordOutboundMessage::create([
            'channel_id' => $channelId,
            'message_type' => 'match_announce',
            'status' => 'pending',
            'payload' => $payload,
            'causer_user_id' => auth()->id(),
        ]);
    }
}
