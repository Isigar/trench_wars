<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\TournamentStandingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md § Models § TournamentStanding +
 *         06-03-PLAN.md <interfaces>.
 *
 * Denormalised standings row — one per (stage, participant). Round-robin
 * tournaments can carry multiple stages (groups + playoffs) where the same
 * participant has DIFFERENT standings, hence the UNIQUE composite is at the
 * stage level (D-06-02-B).
 *
 * `points` + `tiebreak_score` cast as decimal(2) for float-safe arithmetic
 * (Swiss draws are 0.5 each; decimal(8,2) at the DB layer). `rank` is nullable
 * — it's NULL until StandingsCalculatorService runs first (plan 06-09).
 */
class TournamentStanding extends Model
{
    /** @use HasFactory<TournamentStandingFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'tournament_id',
        'tournament_stage_id',
        'participant_id',
        'wins',
        'losses',
        'draws',
        'points',
        'tiebreak_score',
        'median_buchholz',
        'rank',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'wins' => 'integer',
            'losses' => 'integer',
            'draws' => 'integer',
            'points' => 'decimal:2',
            'tiebreak_score' => 'decimal:2',
            'median_buchholz' => 'decimal:2',
            'rank' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $event): string => "TournamentStanding {$event}");
    }

    /** @return BelongsTo<Tournament, $this> */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /** @return BelongsTo<TournamentStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(TournamentStage::class, 'tournament_stage_id');
    }

    /** @return BelongsTo<TournamentParticipant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'participant_id');
    }
}
