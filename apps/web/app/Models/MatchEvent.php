<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Carbon\CarbonInterface;
use Database\Factories\MatchEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .planning/phases/08-rcon-automation/08-04-PLAN.md task 1 +
 *         08-02 migration `2026_05_16_100300_create_match_events_table.php`.
 *
 * Append-only stream of normalised CRCON events per match. The RCON worker
 * (plan 08-10) normalises CRCON wire-format into the canonical event_type enum
 * and POSTs to the web tier; MatchEventIngestService (plan 08-07) writes rows
 * and relies on the composite UNIQUE `(match_id, crcon_stream_id)` to absorb
 * duplicate POSTs on worker reconnect (T-08-04-01).
 *
 * `payload` jsonb is the normalised event body — shape varies by event_type;
 * see plan's <interfaces> table for the canonical shapes. `ingested_at` has a
 * Postgres `DEFAULT now()` and is read-only from PHP (no fillable entry).
 *
 * D-04-03-A LOCKED: match() refers to App\Models\GameMatch (NOT Match). FK
 * column is `match_id` per D-04-03-B.
 *
 * D-012: LogsActivity logs creation only — these rows are never updated nor
 * deleted in user-facing flows. The audit log is a non-repudiation trail for
 * "the worker said the kill happened at T".
 */
class MatchEvent extends Model
{
    /** @use HasFactory<MatchEventFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /**
     * Disable Eloquent's standard `created_at` / `updated_at` columns —
     * `match_events` is an append-only stream with its own timeline columns
     * (`occurred_at` set by the CRCON normaliser + `ingested_at` defaulted by
     * Postgres `DEFAULT now()`). The 08-02 migration deliberately omits
     * `timestamps()`; treating the model as timestamped would attempt to
     * INSERT a non-existent `updated_at` column and 42703.
     */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'match_id',
        'event_type',
        'crcon_action',
        'crcon_stream_id',
        'payload',
        'occurred_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'ingested_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "MatchEvent {$event}");
    }

    /** @return BelongsTo<GameMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    /**
     * Filter events by canonical event_type (one of the 10 CHECK-enum values).
     * Consumed by MatchPlayerStatAggregator (plan 08-08) — `->ofType('player_kill')`.
     *
     * @param  Builder<MatchEvent>  $query
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('event_type', $type);
    }

    /**
     * Filter events whose occurred_at >= the given lower bound. Used by
     * MatchPlayerStatAggregator to scan only events within the match window
     * (CRCON can stream historical events on reconnect; plan 08-07 guards via
     * the booking range).
     *
     * @param  Builder<MatchEvent>  $query
     */
    public function scopeSince(Builder $query, CarbonInterface $when): void
    {
        $query->where('occurred_at', '>=', $when);
    }
}
