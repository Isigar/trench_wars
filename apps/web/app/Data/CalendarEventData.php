<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Article;
use App\Models\Event;
use App\Models\GameMatch;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/07-cms/07-09-PLAN.md <interfaces> CalendarEventData
 *         block + 07-RESEARCH.md Pattern 7 (FullCalendar polymorphic feed).
 *
 * FullCalendar-shaped DTO returned by /events/feed.json. The Vue Events/Index
 * page (plan 07-10) mounts FullCalendar with `events: '/events/feed.json'`;
 * FullCalendar issues GET ?start=...&end=... per-view and renders each event
 * via the `id/title/start/end/url/color` properties on the row.
 *
 * Pitfall 11 (07-RESEARCH): `start` is emitted as Carbon::toIso8601String()
 * which always includes an explicit `+00:00` offset (UTC). FullCalendar's
 * Date parser respects the offset; emitting a naive local string would let
 * the client misinterpret server-side UTC as the user's local timezone and
 * shift events by 1-12 hours. NEVER emit ->format('Y-m-d H:i:s') here.
 *
 * Pattern 7 morphTo resolution: Event::eventable is morphTo (eventable_type +
 * eventable_id). The eventable_type column stores the canonical FQN
 * ('App\Models\GameMatch' / 'App\Models\Tournament' / 'App\Models\Article')
 * because no morphMap is registered. `$event->eventable` triggers an Eloquent
 * lookup against the resolved class. fromModel() switches on `instanceof` so
 * the discriminator type ('match'|'tournament'|'article') stays stable across
 * morphMap rewrites.
 *
 * Open Question 6 LOCKED (07-CONTEXT.md): colour palette per event type.
 *   match=#3B82F6 (Tailwind blue-500), tournament=#8B5CF6 (Tailwind violet-500),
 *   article=#10B981 (Tailwind emerald-500). Hex matches Phase 6 tournament
 *   bracket card accent + Phase 7 article card accent so the calendar reads
 *   the same visual vocabulary the rest of the site uses.
 *
 * Caller (EventsFeedJsonController + CalendarFeedService) MUST eager-load
 * `eventable` on the Event collection so fromModel() does not fire one extra
 * SELECT per event row (T-07-09-04 amplification mitigation).
 */
#[TypeScript]
final class CalendarEventData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public string $start,
        public ?string $end,
        public string $type,
        public string $url,
        public string $color,
    ) {}

    public static function fromModel(Event $event): self
    {
        /** @var Model|null $owner */
        $owner = $event->eventable;
        $type = self::typeFor($owner);

        /** @var Carbon $startsAt */
        $startsAt = $event->starts_at;
        /** @var Carbon|null $endsAt */
        $endsAt = $event->ends_at;

        $title = $event->getTranslation('title', app()->getLocale(), useFallbackLocale: true);
        if ($title === '') {
            $title = '(untitled)';
        }

        return new self(
            id: $event->id,
            title: $title,
            start: $startsAt->toIso8601String(),
            end: $endsAt?->toIso8601String(),
            type: $type,
            url: self::resolveUrl($type, $owner),
            color: self::colourFor($type),
        );
    }

    private static function typeFor(?Model $owner): string
    {
        return match (true) {
            $owner instanceof GameMatch => 'match',
            $owner instanceof Tournament => 'tournament',
            $owner instanceof Article => 'article',
            default => 'other',
        };
    }

    /**
     * URL resolution per eventable type. Uses named routes so renames propagate
     * automatically. The fallback empty string covers the 'other' case (a future
     * eventable_type that has not yet been wired into the calendar).
     */
    private static function resolveUrl(string $type, ?Model $owner): string
    {
        return match (true) {
            $type === 'match' && $owner instanceof GameMatch => route('matches.show', ['match' => $owner->id]),
            $type === 'tournament' && $owner instanceof Tournament => route('tournaments.show', ['tournament' => $owner->slug]),
            $type === 'article' && $owner instanceof Article => route('blog.show', ['slug' => $owner->slug]),
            default => '',
        };
    }

    /**
     * Open Question 6 LOCKED colour scheme. Hex literals (not Tailwind class
     * names) because FullCalendar consumes the `color` property as a CSS
     * colour string, not a class name.
     */
    private static function colourFor(string $type): string
    {
        return match ($type) {
            'match' => '#3B82F6',
            'tournament' => '#8B5CF6',
            'article' => '#10B981',
            default => '#6B7280',
        };
    }
}
