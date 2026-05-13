<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\TournamentBracketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md § Models § TournamentBracket +
 *         06-03-PLAN.md <interfaces>.
 *
 * Bracket node. Each row is one match position in a stage; carries the two
 * participants, the winner, the materialised GameMatch link, and the two advance
 * pointers (winner advances + loser drop chain for double-elim).
 *
 * D-04-03-B compliance — every cross-class BelongsTo passes an explicit FK arg:
 *   - match()              → BelongsTo<GameMatch, $this>          fk='match_id'
 *   - advancesTo()         → BelongsTo<TournamentBracket, $this>  fk='advances_to_bracket_id'
 *   - loserAdvancesTo()    → BelongsTo<TournamentBracket, $this>  fk='loser_advances_to_bracket_id'
 *   - participantA/B/winner → BelongsTo<TournamentParticipant>    explicit FK args
 *
 * DB-layer invariants enforced by migration 2026_05_15_100300:
 *   - tournament_brackets_no_self_advance CHECK     (Pitfall 11 / T-06-02-02)
 *   - tournament_brackets_match_id_unique  PARTIAL UNIQUE WHERE match_id IS NOT NULL
 *     (Pitfall 4 / T-06-02-03)
 *   - tournament_brackets_stage_position    composite UNIQUE (stage_id, round_number, position)
 */
class TournamentBracket extends Model
{
    /** @use HasFactory<TournamentBracketFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'tournament_stage_id',
        'round_number',
        'position',
        'participant_a_id',
        'participant_b_id',
        'winner_participant_id',
        'match_id',
        'advances_to_bracket_id',
        'loser_advances_to_bracket_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'position' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $event): string => "TournamentBracket {$event}");
    }

    /** @return BelongsTo<TournamentStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(TournamentStage::class, 'tournament_stage_id');
    }

    /** @return BelongsTo<TournamentParticipant, $this> */
    public function participantA(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'participant_a_id');
    }

    /** @return BelongsTo<TournamentParticipant, $this> */
    public function participantB(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'participant_b_id');
    }

    /** @return BelongsTo<TournamentParticipant, $this> */
    public function winnerParticipant(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'winner_participant_id');
    }

    /**
     * D-04-03-A LOCKED + D-04-03-B explicit FK arg: method name `match()` does not
     * match related class `GameMatch`, so Laravel cannot infer the FK column. The
     * explicit `'match_id'` argument matches the migration FK + Phase 4's
     * `matches` table.
     *
     * @return BelongsTo<GameMatch, $this>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    /** @return BelongsTo<TournamentBracket, $this> */
    public function advancesTo(): BelongsTo
    {
        return $this->belongsTo(TournamentBracket::class, 'advances_to_bracket_id');
    }

    /** @return BelongsTo<TournamentBracket, $this> */
    public function loserAdvancesTo(): BelongsTo
    {
        return $this->belongsTo(TournamentBracket::class, 'loser_advances_to_bracket_id');
    }
}
