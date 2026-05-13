<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\GameMatchTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples § Model: GameMatchType.
 *
 * Game-scoped match-type catalogue (D-007). Composite UNIQUE (game_id, key) at DB layer.
 * Both `name` and `description` are JSONB locale-keyed (HasTranslations). `description` is
 * nullable in the migration; Filament Edit page coerces null → ['en' => ''] to keep the
 * HasTranslations accessor sane (Pitfall 2).
 */
class GameMatchType extends Model
{
    /** @use HasFactory<GameMatchTypeFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /** @var list<string> */
    protected $fillable = [
        'game_id',
        'key',
        'name',
        'description',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "GameMatchType {$event}");
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<GameMatchTypeRoleLimit, $this> */
    public function roleLimits(): HasMany
    {
        return $this->hasMany(GameMatchTypeRoleLimit::class, 'game_match_type_id')->orderBy('sort_order');
    }
}
