<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 8 (polymorphic events) +
 *         04-03-PLAN.md <interfaces> Event block.
 *
 * Polymorphic calendar projection. Owner side (GameMatch, future Tournament) sets
 * `morphOne(Event::class, 'eventable')`; this model reads back via `morphTo()` which
 * Laravel resolves from (eventable_type, eventable_id).
 *
 * Population is observer-driven (MatchObserver lands in plan 04-08). The composite
 * UNIQUE `events_one_per_owner` is the defense-in-depth half — one Event row per
 * owner entity.
 *
 * Translatable: `title` is JSONB locale-keyed.
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    public array $translatable = ['title'];

    /** @var list<string> */
    protected $fillable = [
        'eventable_type',
        'eventable_id',
        'starts_at',
        'ends_at',
        'title',
        'is_public',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_public' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "Event {$event}");
    }

    /** @return MorphTo<Model, $this> */
    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }
}
