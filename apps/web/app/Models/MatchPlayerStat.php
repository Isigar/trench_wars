<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use App\Observers\MatchPlayerStatObserver;
use Database\Factories\MatchPlayerStatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .planning/phases/08-rcon-automation/08-04-PLAN.md task 1 +
 *         08-02 migration `2026_05_16_100200_create_match_player_stats_table.php`.
 *
 * Per-player aggregated counters for a single match. Populated by
 * MatchPlayerStatAggregator (plan 08-08) reading match_events on match_end.
 * The composite UNIQUE `(match_id, player_id)` keys the aggregator's
 * `updateOrCreate(...)` — re-aggregation is idempotent.
 *
 * Read by the bot embed (plan 08-12 MVP picker) via `->kdr()` accessor.
 *
 * D-04-03-A LOCKED: match() refers to App\Models\GameMatch (NOT Match). FK
 * column is `match_id` per D-04-03-B.
 *
 * D-012: LogsActivity covers create/update for the rare "admin manually
 * corrects a stat" case (non-repudiation).
 */
class MatchPlayerStat extends Model
{
    /** @use HasFactory<MatchPlayerStatFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'match_id',
        'player_id',
        'kills',
        'deaths',
        'team_kills',
        'score',
        'role_played',
        'weapons_used',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kills' => 'integer',
            'deaths' => 'integer',
            'team_kills' => 'integer',
            'score' => 'integer',
            'weapons_used' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "MatchPlayerStat {$event}");
    }

    /** @return BelongsTo<GameMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    /**
     * Kills/deaths ratio, used by the bot embed MVP picker (plan 08-12).
     * Returns `kills` itself when `deaths === 0` so the accessor is
     * division-by-zero safe (this is the spec from plan 08-04 task 1).
     */
    public function kdr(): float|int
    {
        if ($this->deaths === 0) {
            return $this->kills;
        }

        return round($this->kills / $this->deaths, 2);
    }

    /**
     * Register MatchPlayerStatObserver — Phase 9 plan 09-05 task 2.
     *
     * The observer flushes the `leaderboards` cache tag on every saved()
     * event (created and updated branches consolidated — see observer
     * docblock for the RESEARCH-anti-pattern-exception justification).
     *
     * D-04-08-B precedent — register via model booted() (idempotent;
     * Eloquent dedupes by observer class name).
     */
    protected static function booted(): void
    {
        static::observe(MatchPlayerStatObserver::class);
    }
}
