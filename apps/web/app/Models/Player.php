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

/**
 * Source: .docs/05-database-schema.md § players.
 *
 * `bio` jsonb is translatable in Phase 2+ via spatie/laravel-translatable's
 * HasTranslations trait. P1 ships the column + array cast; the trait wraps it
 * later without breaking changes.
 */
class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use SoftDeletes;

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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bio' => 'array',
        ];
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
