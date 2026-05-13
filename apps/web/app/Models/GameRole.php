<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\GameRoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples § Model: GameRole.
 *
 * Game-scoped role catalogue (D-007). `key` is unique WITHIN a game via composite UNIQUE
 * (game_id, key) at the DB layer. `display_name` is JSONB locale-keyed (HasTranslations).
 */
class GameRole extends Model
{
    /** @use HasFactory<GameRoleFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    public array $translatable = ['display_name'];

    /** @var list<string> */
    protected $fillable = [
        'game_id',
        'key',
        'display_name',
        'sort_order',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "GameRole {$event}");
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<GameMatchTypeRoleLimit, $this> */
    public function roleLimits(): HasMany
    {
        return $this->hasMany(GameMatchTypeRoleLimit::class);
    }
}
