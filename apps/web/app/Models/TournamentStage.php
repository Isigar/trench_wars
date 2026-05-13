<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\TournamentStageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md § Models § TournamentStage +
 *         06-03-PLAN.md <interfaces>.
 *
 * Logical grouping inside a tournament. The 6 stage types map to the 4 LOCKED
 * formats (D-011) — see migration CHECK constraint. UNIQUE(tournament_id, ordinal)
 * prevents two stages claiming the same display position.
 *
 * `brackets()` is ordered by (round_number, position) so generators / standings
 * services get a deterministic walk order.
 */
class TournamentStage extends Model
{
    /** @use HasFactory<TournamentStageFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'tournament_id',
        'type',
        'ordinal',
        'name',
        'settings',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ordinal' => 'integer',
            'settings' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $event): string => "TournamentStage {$event}");
    }

    /** @return BelongsTo<Tournament, $this> */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /** @return HasMany<TournamentBracket, $this> */
    public function brackets(): HasMany
    {
        return $this->hasMany(TournamentBracket::class)
            ->orderBy('round_number')
            ->orderBy('position');
    }
}
