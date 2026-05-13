<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use App\Observers\MatchObserver;
use Database\Factories\GameMatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Code Examples § Model: Match.
 *
 * NAMING DECISION (locked 2026-05-13; supersedes Pitfall 5 / Assumption A4 in 04-RESEARCH.md):
 *
 *   The class is named `GameMatch` (NOT `Match`). The original plan assumed `Match` was a
 *   legal PHP 8 identifier merely with cosmetic friction. That assumption was empirically
 *   wrong on PHP 8.4 — `class Match {}` is a PARSE ERROR:
 *
 *       PHP Parse error: syntax error, unexpected token "match", expecting identifier
 *
 *   `match` is a fully reserved keyword (since PHP 8.0) and cannot be used as a class name
 *   regardless of case-sensitivity of the parser elsewhere. The previous executor verified
 *   this via `docker compose exec web php -r "class Match {}"` and aborted plan 04-03.
 *
 *   Resolution adopted by the autonomous workflow:
 *     - Class name: GameMatch
 *     - Database table: `matches` (unchanged — `protected $table = 'matches';` override)
 *     - FK columns: `match_id` (unchanged — explicit foreign keys on BelongsTo relations)
 *     - Routes / URL slug: `/matches` (unchanged)
 *     - i18n keys: `matches.*` (unchanged)
 *
 *   Rationale:
 *     1. Symmetric with the Phase 3 family: GameMatchType, GameMatchTypeRoleLimit, GameRole.
 *     2. Aligns with D-007 (generic-game model — Match is one game's match-instance entity).
 *     3. Avoids ambiguity at every call site that uses `match($x) { ... }` expressions.
 *     4. Eloquent honors `protected $table` so the DB schema needs no rename.
 *
 *   This decision is binding for plans 04-04..04-13. All subsequent code references the
 *   class as `App\Models\GameMatch` and the table as `matches`.
 *
 * D-010: capacity enforced via MatchSignupService row lock — never write to MatchSlot.occupant_user_id
 * outside that service.
 */
class GameMatch extends Model
{
    /** @use HasFactory<GameMatchFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;

    /**
     * Table name override — class is `GameMatch` but DB schema lives at `matches`
     * (see naming-decision docblock above for rationale).
     */
    protected $table = 'matches';

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    /** @var list<string> */
    protected $fillable = [
        'game_match_type_id',
        'title',
        'description',
        'scheduled_at',
        'organiser_user_id',
        'host_clan_id',
        'server_address',
        'status',
        'is_public',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'is_public' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "Match {$event}");
    }

    /** @return BelongsTo<GameMatchType, $this> */
    public function gameMatchType(): BelongsTo
    {
        return $this->belongsTo(GameMatchType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function organiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organiser_user_id');
    }

    /** @return BelongsTo<Clan, $this> */
    public function hostClan(): BelongsTo
    {
        return $this->belongsTo(Clan::class, 'host_clan_id');
    }

    /** @return HasMany<MatchSlot, $this> */
    public function slots(): HasMany
    {
        return $this->hasMany(MatchSlot::class, 'match_id')
            ->orderBy('sort_order')
            ->orderBy('slot_index');
    }

    /** @return HasMany<MatchAccessRule, $this> */
    public function accessRules(): HasMany
    {
        return $this->hasMany(MatchAccessRule::class, 'match_id');
    }

    /** @return HasOne<MatchResult, $this> */
    public function result(): HasOne
    {
        return $this->hasOne(MatchResult::class, 'match_id');
    }

    /** @return MorphOne<Event, $this> */
    public function event(): MorphOne
    {
        return $this->morphOne(Event::class, 'eventable');
    }

    /**
     * Register MatchObserver for polymorphic Event sync (plan 04-08, Pattern 8).
     *
     * Static::observe is idempotent — Eloquent dedupes by class name, so repeat
     * registrations from AppServiceProvider or repeated booted() invocations are
     * harmless.
     */
    protected static function booted(): void
    {
        static::observe(MatchObserver::class);
    }
}
