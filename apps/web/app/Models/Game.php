<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples § Model: Game.
 *
 * Generic Game catalogue (D-007). `name` is JSONB locale-keyed (HasTranslations).
 * `key` is the slug-safe identifier — DB CHECK enforces `^[a-z0-9_]+$` (Pitfall 5).
 *
 * Roles + match types cascade-delete with the parent (Pitfall 7); RoleLimits cascade
 * further via the game_match_type_role_limits dual FKs.
 */
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var list<string> */
    protected $fillable = [
        'key',
        'name',
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
            ->setDescriptionForEvent(fn (string $event): string => "Game {$event}");
    }

    /** @return HasMany<GameRole, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(GameRole::class)->orderBy('sort_order');
    }

    /** @return HasMany<GameMatchType, $this> */
    public function matchTypes(): HasMany
    {
        return $this->hasMany(GameMatchType::class);
    }
}
