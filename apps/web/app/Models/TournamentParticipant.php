<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\TournamentParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md § Models § TournamentParticipant +
 *         06-03-PLAN.md <interfaces>.
 *
 * Tournament-Clan join carrying seed/status/placement. The composite
 * UNIQUE(tournament_id, clan_id) is the DB defence against double registration
 * (T-06-02-04 mitigation); `status` is CHECK-defended to four lifecycle values
 * (registered, active, withdrawn, disqualified) in the migration.
 *
 * `bracketsAsA` / `bracketsAsB` / `bracketsAsWinner` expose the three FK columns
 * back to tournament_brackets — useful for "show me every bracket this
 * participant has appeared in" queries.
 */
class TournamentParticipant extends Model
{
    /** @use HasFactory<TournamentParticipantFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'tournament_id',
        'clan_id',
        'seed',
        'status',
        'placement',
        'registered_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'placement' => 'integer',
            'registered_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $event): string => "TournamentParticipant {$event}");
    }

    /** @return BelongsTo<Tournament, $this> */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /** @return BelongsTo<Clan, $this> */
    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    /** @return HasMany<TournamentBracket, $this> */
    public function bracketsAsA(): HasMany
    {
        return $this->hasMany(TournamentBracket::class, 'participant_a_id');
    }

    /** @return HasMany<TournamentBracket, $this> */
    public function bracketsAsB(): HasMany
    {
        return $this->hasMany(TournamentBracket::class, 'participant_b_id');
    }

    /** @return HasMany<TournamentBracket, $this> */
    public function bracketsAsWinner(): HasMany
    {
        return $this->hasMany(TournamentBracket::class, 'winner_participant_id');
    }
}
