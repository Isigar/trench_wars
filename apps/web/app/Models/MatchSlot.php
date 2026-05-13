<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\MatchSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 1 (match_slots) +
 *         04-03-PLAN.md <interfaces> MatchSlot block.
 *
 * Capacity-row for a single (match, role, slot_index). Two DB-layer invariants protect
 * this table:
 *   1. Composite UNIQUE (match_id, game_role_id, slot_index) — no duplicate slot rows.
 *   2. Partial UNIQUE (match_id, occupant_user_id) WHERE occupant_user_id IS NOT NULL —
 *      a user can occupy at most one slot per match (D-009 analog for matches).
 *
 * D-010 / T-04-03-02..03 (defense-in-depth):
 *   `occupant_user_id` IS in $fillable so the materialiser (plan 04-05) can seed rows.
 *   BUT the production write path is `MatchSignupService` (plan 04-06) — never call
 *   `$slot->update(['occupant_user_id' => ...])` from a controller. The partial UNIQUE
 *   above is the DB-layer guard; the service is the application-layer guard; LogsActivity
 *   is the audit guard (D-012).
 */
class MatchSlot extends Model
{
    /** @use HasFactory<MatchSlotFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'match_id',
        'game_role_id',
        'slot_index',
        'occupant_user_id',
        'confirmed_at',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'slot_index' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "MatchSlot {$event}");
    }

    /** @return BelongsTo<GameMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    /** @return BelongsTo<GameRole, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(GameRole::class, 'game_role_id');
    }

    /** @return BelongsTo<User, $this> */
    public function occupantUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'occupant_user_id');
    }
}
