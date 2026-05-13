<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\GameMatchTypeRoleLimitFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .planning/phases/03-games-match-types/03-RESEARCH.md § Code Examples §
 * Model: GameMatchTypeRoleLimit.
 *
 * Capacity matrix (MatchType, Role) → capacity. The cross-game invariant
 * (matchType.game_id === role.game_id) is enforced ONLY at the model layer because
 * Postgres cannot express a cheap cross-table CHECK (Pitfall 10 / Assumption A6).
 *
 * Defense-in-depth stack for the cross-game invariant:
 *   1. Filament Edit form Select is game-scoped (plan 03-07) — UI cannot offer cross-game pairs.
 *   2. The `saving()` listener below catches API/Console writes that bypass the UI.
 *
 * No HasTranslations: RoleLimit has no translatable fields.
 */
class GameMatchTypeRoleLimit extends Model
{
    /** @use HasFactory<GameMatchTypeRoleLimitFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'game_match_type_id',
        'game_role_id',
        'capacity',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "GameMatchTypeRoleLimit {$event}");
    }

    /**
     * Cross-game invariant guard (Pitfall 10).
     *
     * Triggered on every save (insert + update). When BOTH the matchType and role parent
     * rows are reachable AND their `game_id` differs, throw DomainException — this catches
     * API/Console writes that bypass the Filament Select's game-scoped option list.
     *
     * When EITHER side is null (e.g. brand-new model with both FKs newly set), the `?->`
     * operator lazy-loads the BelongsTo relation via the FK; only when both relations
     * resolve and disagree do we throw. Mismatched-game pairs cannot be persisted.
     */
    protected static function booted(): void
    {
        static::saving(function (self $limit): void {
            $matchTypeGameId = $limit->matchType?->game_id;
            $roleGameId = $limit->role?->game_id;

            if ($matchTypeGameId !== null && $roleGameId !== null && $matchTypeGameId !== $roleGameId) {
                throw new DomainException(
                    'GameMatchTypeRoleLimit: matchType.game_id and role.game_id must match.'
                );
            }
        });
    }

    /** @return BelongsTo<GameMatchType, $this> */
    public function matchType(): BelongsTo
    {
        return $this->belongsTo(GameMatchType::class, 'game_match_type_id');
    }

    /** @return BelongsTo<GameRole, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(GameRole::class, 'game_role_id');
    }
}
