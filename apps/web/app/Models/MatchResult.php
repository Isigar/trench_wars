<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use App\Observers\MatchResultObserver;
use Database\Factories\MatchResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 1 (match_results) +
 *         04-03-PLAN.md <interfaces> MatchResult block.
 *
 * 1:1 with matches (match_id UNIQUE at DB layer). Scores are nullable (results may be
 * filed before scores are known) but `match_results_scores_nonneg_check` rejects negative
 * integers (T-04-02-04). Cascades on parent match delete; cascades down to match_mvps.
 */
class MatchResult extends Model
{
    /** @use HasFactory<MatchResultFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'match_id',
        'winner_clan_id',
        'allies_score',
        'axis_score',
        'notes',
        'recorded_by_user_id',
        'recorded_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'allies_score' => 'integer',
            'axis_score' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "MatchResult {$event}");
    }

    /** @return BelongsTo<GameMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    /** @return BelongsTo<Clan, $this> */
    public function winnerClan(): BelongsTo
    {
        return $this->belongsTo(Clan::class, 'winner_clan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** @return HasMany<MatchMvp, $this> */
    public function mvps(): HasMany
    {
        return $this->hasMany(MatchMvp::class, 'match_result_id');
    }

    /**
     * Register MatchResultObserver — Phase 6 plan 06-08 Task 2.
     *
     * The observer fires BracketAdvancementService::advance() on relevant
     * MatchResult saves (wasChanged guard inside the observer). Phase 4
     * shipped MatchResult without a booted() method; this is the first
     * observer attached to the model. Adding via booted() (Phase 4 idiom,
     * D-04-08-B) keeps the observer registration colocated with the model
     * rather than centralised in AppServiceProvider.
     */
    protected static function booted(): void
    {
        static::observe(MatchResultObserver::class);
    }
}
