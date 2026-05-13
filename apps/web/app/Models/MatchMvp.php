<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\MatchMvpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 1 (match_mvps) +
 *         04-03-PLAN.md <interfaces> MatchMvp block.
 *
 * Per-category MVP entry for a match result. A player may appear in multiple categories
 * (e.g. kills AND objective) of the same result, but a (result, category, player) triple
 * is unique — composite UNIQUE `match_mvps_unique` enforces this.
 *
 * Category is one of {'kills','defense','objective','mvp'} — DB CHECK `match_mvps_category_check`
 * is the defense-in-depth half (T-04-02-03).
 *
 * Cascades on parent MatchResult delete (which itself cascades from parent GameMatch).
 */
class MatchMvp extends Model
{
    /** @use HasFactory<MatchMvpFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'match_result_id',
        'player_id',
        'category',
        'value',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "MatchMvp {$event}");
    }

    /** @return BelongsTo<MatchResult, $this> */
    public function result(): BelongsTo
    {
        return $this->belongsTo(MatchResult::class, 'match_result_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
