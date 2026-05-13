<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DiscordOutboundMessage;
use App\Models\Event;
use App\Models\Tournament;
use App\Support\DiscordOutboundPayloadBuilder;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-10-PLAN.md Task 2 +
 *         06-RESEARCH.md § TournamentObserver polymorphic Event sync +
 *         Phase 4 plan 04-08 MatchObserver mirror.
 *
 * Replaces the plan 06-03 stub with real bodies. Three side-effects per
 * Tournament write:
 *
 *   1. (Phase 4 polymorphic Event mirror) saved() keeps the polymorphic
 *      `events` row coherent with `tournaments.is_public` and
 *      `tournaments.status` for the unified `/events` calendar.
 *
 *   2. (Phase 5 plan 05-05 + D-05-05-A) created() enqueues a pending
 *      `discord_outbound_messages` row with `message_type='tournament_announce'`
 *      whenever a public Tournament is created. The bot worker (plan 05-11)
 *      picks the row up via the Pattern 4 atomic claim and renders an embed.
 *
 *   3. (Pitfall 7 mitigation) updated() enqueues a follow-up
 *      `tournament_announce_update` row ONLY when `wasChanged('status')` AND
 *      the tournament is public. Title/description/etc. edits do NOT fire
 *      outbound rows — channel-spam mitigation per T-06-10-02.
 *
 * Threat refs:
 *   - T-06-10-01 (Information Disclosure): is_public guard at the top of
 *     created()/updated() suppresses outbound writes for private tournaments.
 *   - T-06-10-02 (Repudiation — double-fire on cascade observer chain):
 *     updated() gates on wasChanged('status'); title edits do NOT enqueue.
 *
 * channel_id: the discord_outbound_messages.channel_id column is `text NOT NULL`
 * (migration 2026_05_13_170625). The bot worker resolves the channel at
 * dispatch time from the organising clan / tournament-wide config; we write
 * an empty string here as the convention established by BracketAdvancementService
 * (plan 06-08, commit a07e84a — see line 191).
 */
class TournamentObserver
{
    /**
     * Upsert/delete the Event row to mirror the tournament's public + non-cancelled
     * state. Runs inside the same DB::transaction as the model save (Eloquent
     * default), so an outer rollback discards this write too.
     *
     * starts_at can legitimately be null on a Tournament (draft state with no
     * scheduled start); we default to now() so the Event row is still created
     * — visitors browsing /events see the draft slot at "now" and the admin
     * can update later via Filament (Phase 6 plan 06-11).
     */
    public function saved(Tournament $tournament): void
    {
        $shouldHaveEvent = $tournament->is_public && $tournament->status !== 'cancelled';

        if ($shouldHaveEvent) {
            Event::updateOrCreate(
                ['eventable_type' => Tournament::class, 'eventable_id' => $tournament->id],
                [
                    'starts_at' => $tournament->starts_at ?? now(),
                    'ends_at' => $tournament->ends_at,
                    'title' => $tournament->getTranslations('title'),
                    'is_public' => $tournament->is_public,
                ],
            );

            return;
        }

        Event::where('eventable_type', Tournament::class)
            ->where('eventable_id', $tournament->id)
            ->delete();
    }

    /**
     * Enqueue tournament_announce when a NEW public tournament lands. Fires
     * AFTER saved() (Eloquent order: saving → creating → [persist] → created →
     * saved). The Event row is therefore already present (or absent) by the
     * time we run.
     */
    public function created(Tournament $tournament): void
    {
        if (! $tournament->is_public) {
            return;
        }

        DiscordOutboundMessage::create([
            'channel_id' => '',  // resolved at dispatch time by the bot renderer
            'message_type' => 'tournament_announce',
            'status' => 'pending',
            'payload' => DiscordOutboundPayloadBuilder::buildTournamentAnnounce($tournament),
            'causer_user_id' => auth()->id(),
        ]);
    }

    /**
     * Enqueue tournament_announce_update ONLY when the status column flipped
     * (wasChanged('status') is the SOLE gate — Pitfall 7 / T-06-10-02). A
     * private tournament still skips the outbound; a status flip on a private
     * tournament is meaningless to public viewers.
     */
    public function updated(Tournament $tournament): void
    {
        if (! $tournament->wasChanged('status')) {
            return;
        }

        if (! $tournament->is_public) {
            return;
        }

        DiscordOutboundMessage::create([
            'channel_id' => '',  // resolved at dispatch time by the bot renderer
            'message_type' => 'tournament_announce_update',
            'status' => 'pending',
            'payload' => DiscordOutboundPayloadBuilder::buildTournamentAnnounce($tournament),
            'causer_user_id' => auth()->id(),
        ]);
    }
}
