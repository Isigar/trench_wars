<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Event;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 8 (polymorphic events) +
 *         04-07-PLAN.md <interfaces> EventData block.
 *
 * Polymorphic calendar projection. `eventable_type` is the owner-model FQN
 * (e.g. 'App\\Models\\GameMatch') and `eventable_id` is the owner UUID. Vue
 * components compare by FQN string when routing the event to its detail page.
 *
 * Translatable: `title` is JSONB locale-keyed; the `?: null` null-coalesce
 * pattern collapses empty arrays to null (Phase 3 Pitfall 4).
 */
#[TypeScript]
final class EventData extends Data
{
    /**
     * @param  array<string, string>|null  $title
     */
    public function __construct(
        public string $id,
        public string $eventable_type,
        public string $eventable_id,
        public string $starts_at,
        public ?string $ends_at,
        public ?array $title,
        public bool $is_public,
    ) {}

    /**
     * Build an EventData from an Event Eloquent model.
     */
    public static function fromModel(Event $event): self
    {
        /** @var Carbon $startsAt */
        $startsAt = $event->starts_at;
        /** @var Carbon|null $endsAt */
        $endsAt = $event->ends_at;

        return new self(
            id: $event->id,
            eventable_type: $event->eventable_type,
            eventable_id: $event->eventable_id,
            starts_at: $startsAt->toIso8601String(),
            ends_at: $endsAt?->toIso8601String(),
            title: $event->getTranslations('title') ?: null,
            is_public: $event->is_public,
        );
    }
}
