<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\MatchAccessRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 1 (match_access_rules) +
 *         04-03-PLAN.md <interfaces> MatchAccessRule block.
 *
 * Tag-based signup gate: when zero rules exist for a match, the match is open to all
 * eligible players. When >=1 rule exists, only players whose ACTIVE clan carries one of
 * the listed tags may sign up (D-009 + RESEARCH Pattern 5 tag-access logic — consumed by
 * MatchSignupService in plan 04-06).
 *
 * Composite UNIQUE (match_id, clan_tag_id) blocks duplicate rule rows (T-04-02 / T-04-03).
 */
class MatchAccessRule extends Model
{
    /** @use HasFactory<MatchAccessRuleFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'match_id',
        'clan_tag_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "MatchAccessRule {$event}");
    }

    /** @return BelongsTo<GameMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    /** @return BelongsTo<ClanTag, $this> */
    public function clanTag(): BelongsTo
    {
        return $this->belongsTo(ClanTag::class, 'clan_tag_id');
    }
}
