<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use App\Observers\TournamentObserver;
use Database\Factories\TournamentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-RESEARCH.md § Models § Tournament +
 *         06-03-PLAN.md <interfaces>.
 *
 * Phase 6 root entity. Canonical Phase 4 idiom (verbatim from GameMatch.php):
 *   - HasUuidPrimaryKey (UUIDv4 from pgcrypto)
 *   - HasFactory<TournamentFactory>
 *   - LogsActivity (D-012 audit)
 *   - HasTranslations on title + description (D-013)
 *
 * Route binding: `slug` (URL shape /tournaments/{slug}). Mirrors Clan + Game routing.
 *
 * Observer registration via booted() (Phase 4 plan 04-08 idiom). The observer ships
 * empty in plan 06-03 and gets real bodies in plan 06-10.
 *
 * The four LOCKED formats (D-011) and six lifecycle states are defended by DB CHECK
 * constraints (see migration 2026_05_15_100000_create_tournaments_table.php):
 *   format: single_elimination | double_elimination | round_robin | swiss
 *   status: draft | registering | seeded | running | completed | cancelled
 */
class Tournament extends Model
{
    /** @use HasFactory<TournamentFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    /** @var list<string> */
    protected $fillable = [
        'game_id',
        'slug',
        'title',
        'description',
        'format',
        'status',
        'starts_at',
        'ends_at',
        'max_participants',
        'settings',
        'organiser_user_id',
        'default_game_match_type_id',
        'is_public',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_participants' => 'integer',
            'settings' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $event): string => "Tournament {$event}");
    }

    /**
     * Route model binding uses slug (e.g. /tournaments/{slug}).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<User, $this> */
    public function organiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organiser_user_id');
    }

    /** @return BelongsTo<GameMatchType, $this> */
    public function defaultGameMatchType(): BelongsTo
    {
        return $this->belongsTo(GameMatchType::class, 'default_game_match_type_id');
    }

    /** @return HasMany<TournamentParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    /** @return HasMany<TournamentStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(TournamentStage::class)->orderBy('ordinal');
    }

    /** @return HasMany<TournamentStanding, $this> */
    public function standings(): HasMany
    {
        return $this->hasMany(TournamentStanding::class);
    }

    /** @return MorphOne<Event, $this> */
    public function event(): MorphOne
    {
        return $this->morphOne(Event::class, 'eventable');
    }

    /**
     * Register TournamentObserver. The stub bodies live in plan 06-03; plan
     * 06-10 fills them with the real Event-sync + Discord outbound logic.
     */
    protected static function booted(): void
    {
        static::observe(TournamentObserver::class);
    }
}
