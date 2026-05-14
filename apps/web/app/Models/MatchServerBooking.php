<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Carbon\CarbonInterface;
use Database\Factories\MatchServerBookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .planning/phases/08-rcon-automation/08-03-PLAN.md task 1 +
 *         08-02 migration `2026_05_16_100100_create_match_server_bookings_table.php`.
 *
 * Per-match server reservation row. Postgres EXCLUDE constraint
 * `match_server_bookings_no_overlap` (T-08-02-01 mitigation) prevents two ACTIVE
 * bookings of the same server from claiming overlapping `[reserved_from, reserved_to)`
 * windows. Half-open `[)` range ⇒ back-to-back bookings sharing an endpoint are OK;
 * partial `WHERE status='active'` ⇒ cancelled bookings free their slot.
 *
 * D-04-03-A LOCKED: relations refer to App\Models\GameMatch (NOT Match). FK column is
 * `match_id` per D-04-03-B.
 */
class MatchServerBooking extends Model
{
    /** @use HasFactory<MatchServerBookingFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'match_id',
        'server_id',
        'reserved_from',
        'reserved_to',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reserved_from' => 'datetime',
            'reserved_to' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "MatchServerBooking {$event}");
    }

    /** @return BelongsTo<GameMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    /** @return BelongsTo<MatchServer, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(MatchServer::class, 'server_id');
    }

    /**
     * Active bookings only — the booking scheduler (plan 08-11) ignores cancelled
     * and completed rows.
     *
     * @param  Builder<MatchServerBooking>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    /**
     * Bookings whose window overlaps the given `[from, to]` interval. Used by the
     * booking-poller (plan 08-11) to find bookings due within the next polling
     * window. Half-open semantics mirror the EXCLUDE constraint.
     *
     * @param  Builder<MatchServerBooking>  $query
     */
    public function scopeDueWithin(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('reserved_from', '<=', $to)
            ->where('reserved_to', '>=', $from);
    }
}
