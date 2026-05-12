<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Source: .docs/05-database-schema.md § players.
 *
 * `bio` jsonb is translatable in Phase 2+ via spatie/laravel-translatable's
 * HasTranslations trait. P1 ships the column + array cast; the trait wraps it
 * later without breaking changes. Plan 14 adds LogsActivity (D-012).
 */
class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['bio'];

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'slug',
        'display_name',
        'avatar_source',
        'avatar_path',
        'bio',
        'country_code',
    ];

    /**
     * Activity log options for Player mutations.
     *
     * Source: 01-14-PLAN.md task 1 must_haves — log fillable diffs only.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "Player {$event}");
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<PlayerPrivacy, $this> */
    public function privacy(): HasOne
    {
        return $this->hasOne(PlayerPrivacy::class);
    }
}
