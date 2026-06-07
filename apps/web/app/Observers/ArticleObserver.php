<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Article;
use App\Models\DiscordOutboundMessage;
use App\Support\DiscordOutboundPayloadBuilder;

/**
 * Source: .planning/phases/07-cms/07-06-PLAN.md Task 2 + 07-RESEARCH.md +
 *         Phase 6 plan 06-10 TournamentObserver D-06-08-A two-hook pattern
 *         (created + updated, NOT saved) verbatim.
 *
 * Two side-effects per Article write:
 *
 *   1. Event MorphOne sync — keep the polymorphic `events` row coherent with
 *      Article.status + Article.published_at so the unified /events calendar
 *      (plan 07-09 EventsFeedJsonController) returns article events alongside
 *      Match + Tournament events. The Article events row stays present for
 *      drafts (with is_public=false) — Phase 4 D-04-08-C precedent — so
 *      historical visibility flips don't churn rows.
 *
 *   2. Discord article_announce outbound enqueue — fires ONLY on the first
 *      transition to status='published' (Pitfall 10 in observer — republish
 *      MUST NOT spam Discord). Gated by three conditions:
 *         (a) status === 'published'
 *         (b) getOriginal('status') !== 'published' (first transition only)
 *         (c) $a->allow_discord_announce === true (per-article opt-in)
 *         (d) config('discord.league_announce_channel_id') is non-empty
 *             (defensive — no global channel configured = no outbound row)
 *
 * Registered via Article::booted() — D-06-08-A model-level registration
 * pattern. Bulk updates (Article::query()->update(...)) bypass model events
 * and therefore this observer — operators MUST iterate models for any bulk
 * publish flow.
 *
 * channel_id column convention: discord_outbound_messages.channel_id is
 * text NOT NULL. The bot worker resolves the actual destination channel at
 * dispatch time from config('discord.league_announce_channel_id'); we write
 * an empty string here as the convention established by Phase 6 plan 06-08
 * (BracketAdvancementService) + plan 06-10 (TournamentObserver).
 *
 * Threat refs:
 *   - T-07-06-01 (Repudiation — re-publish loop spamming Discord): three-gate
 *     fire-once guard in updated().
 *   - T-07-06-04 (Repudiation — scheduler-driven null causer): accepted;
 *     auth()->id() returns null in CLI/cron context. activity_log on
 *     DiscordOutboundMessage captures the row creation regardless.
 *   - T-07-06-07 (DoS via rapid status flips): onPublish gates on first
 *     transition; subsequent flips no-op.
 */
class ArticleObserver
{
    /**
     * Saving hook — stamp published_at on any path that sets status='published'
     * without an explicit timestamp.
     *
     * The Filament edit form publishes by flipping the status Select to
     * 'published' (its published_at DateTimePicker is ->disabled() so the form
     * never submits one, and EditArticle has no afterSave hook). Without this,
     * a form-published article has published_at=null and sorts unpredictably
     * under orderByDesc('published_at') on /blog. ArticleStatusService sets
     * published_at explicitly, so this only fills the gap when it is still null
     * (and re-publishing an already-published article preserves the original).
     */
    public function saving(Article $a): void
    {
        if ($a->status === 'published' && $a->published_at === null) {
            // setAttribute (not direct property assignment) mirrors the array-update
            // idiom in ArticleStatusService and keeps the datetime cast happy without
            // tripping the larastan string|null property inference.
            $a->setAttribute('published_at', now());
        }
    }

    /**
     * Created hook — covers the rare insert-as-published path (admin or
     * seeder directly creating an Article with status='published') + always
     * syncs the Event row.
     */
    public function created(Article $a): void
    {
        if ($a->status === 'published') {
            $this->onPublish($a);
        }
        $this->syncEvent($a);
    }

    /**
     * Updated hook — D-06-08-A two-hook pattern. wasChanged('status') gates
     * the announce fire; title/excerpt/category edits on a published article
     * MUST refresh the Event row but MUST NOT re-fire the Discord announce.
     */
    public function updated(Article $a): void
    {
        // Pitfall 10 in observer — three-gate fire-once guard. Order matters:
        // wasChanged() is the cheapest predicate; check it first.
        if (
            $a->wasChanged('status')
            && $a->status === 'published'
            && $a->getOriginal('status') !== 'published'
        ) {
            $this->onPublish($a);
        }
        $this->syncEvent($a);
    }

    /**
     * Upsert the Event MorphOne row to mirror current article state.
     *
     * Decision: events table is the calendar substrate; drafts keep their
     * Event row with is_public=false (Phase 4 D-04-08-C precedent) — calendar
     * feed (plan 07-09) filters on is_public so drafts naturally hide on the
     * public calendar surface. Operators get a stable Event identity per
     * Article that survives draft↔published flips without churn.
     */
    private function syncEvent(Article $a): void
    {
        $a->events()->updateOrCreate(
            ['eventable_type' => $a->getMorphClass(), 'eventable_id' => $a->id],
            [
                'is_public' => $a->status === 'published',
                'title' => $a->getTranslations('title'),
                'starts_at' => $a->published_at ?? $a->scheduled_at ?? $a->created_at,
                'ends_at' => null,  // articles are point-in-time events
            ]
        );
    }

    /**
     * Emit the article_announce outbound row. Caller has already gated on
     * "first transition to this save's published state" — this method enforces
     * three additional defences:
     *
     *   1. allow_discord_announce per-article opt-in (T-07-06-01 mitigation #1)
     *   2. config('discord.league_announce_channel_id') is non-empty
     *      (defensive — no global channel configured = no outbound row)
     *   3. Pitfall 10 republish guard — if ANY prior article_announce outbound
     *      row already exists for this article (regardless of status pending /
     *      dispatching / sent / failed), do NOT emit a second one. This handles
     *      the published → draft → published republish loop: even though
     *      wasChanged('status')+getOriginal()!='published' both pass on the
     *      second publish, the prior outbound row blocks the duplicate.
     *      (T-07-06-01 mitigation #2 — threat model line: "ArticleObserverTest
     *      asserts republish does NOT duplicate outbound".)
     */
    private function onPublish(Article $a): void
    {
        if (! $a->allow_discord_announce) {
            return;
        }

        /** @var string $channelId */
        $channelId = config('discord.league_announce_channel_id', '');
        if ($channelId === '') {
            return;  // defensive — no global channel configured
        }

        // Pitfall 10 republish guard — Article id is encoded as payload.article_id
        // by DiscordOutboundPayloadBuilder::buildArticleAnnounce. JSONB ->> on
        // Postgres handles the lookup; matches Phase 5 MatchObserver's
        // payload->match_id idiom.
        $priorExists = DiscordOutboundMessage::query()
            ->where('message_type', 'article_announce')
            ->where('payload->article_id', $a->id)
            ->exists();
        if ($priorExists) {
            return;
        }

        DiscordOutboundMessage::create([
            'channel_id' => '',  // resolved at dispatch via config lookup (D-06-10-E precedent)
            'message_type' => 'article_announce',
            'status' => 'pending',
            'payload' => DiscordOutboundPayloadBuilder::buildArticleAnnounce($a),
            'causer_user_id' => auth()->id(),  // null when scheduler-driven (T-07-06-04 accept)
        ]);
    }
}
