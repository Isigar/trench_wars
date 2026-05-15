<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * Source: .docs/05-database-schema.md § players.
 *
 * `bio` jsonb is translatable in Phase 2+ via spatie/laravel-translatable's
 * HasTranslations trait. P1 ships the column + array cast; the trait wraps it
 * later without breaking changes. Plan 14 adds LogsActivity (D-012).
 *
 * Phase 9 plan 09-09 (Wave 6 — WebP image variants): InteractsWithMedia trait
 * registers avatar conversions matching Clan's shape (avatar-thumb 48x48,
 * avatar-card 200x200, avatar-hero 800x800) so player + clan avatars render
 * with the same sizing budget across surfaces. All ->format('webp')->queued().
 */
class Player extends Model implements HasMedia
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['bio'];

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'slug',
        'display_name',
        'avatar_source',
        'avatar_path',
        'bio',
        'country_code',
        // Phase 8 plan 08-08 — steam_id_64 (nullable, UNIQUE) added so the RCON
        // MatchPlayerStatAggregator can resolve CRCON event payloads to Player rows
        // via `Player::firstWhere('steam_id_64', $payload['steam_id_64'])`.
        // Round-1 acceptance assumes admins backfill before scrim; orphan events
        // (no matching Player row) are silently skipped per Pitfall 5.
        'steam_id_64',
    ];

    /**
     * Activity log options for Player mutations.
     *
     * Source: 01-14-PLAN.md task 1 must_haves — log fillable diffs only.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "Player {$event}");
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Route model binding uses slug (e.g. /players/{slug}).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasOne<PlayerPrivacy, $this> */
    public function privacy(): HasOne
    {
        return $this->hasOne(PlayerPrivacy::class);
    }

    /**
     * Phase 9 plan 09-09 task 1 — WebP avatar conversion trio (matches Clan shape).
     *
     * Player avatars use the SAME conversion names + dimensions as Clan logos so
     * a single Vue component (PlayerAvatar / ClanLogo) can be authored against
     * a consistent variant set without per-model branching in the markup.
     *
     * Open Question 1 LOCKED — WebP only, no JPEG fallback in v1.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Chain order: ->queued / ->format FIRST (Conversion receiver), ->width /
        // ->height SECOND (ImageDriver-proxied) — see Clan::registerMediaConversions
        // docblock for the PHPStan @mixin ImageDriver rationale.
        $this->addMediaConversion('avatar-thumb')
            ->queued()
            ->format('webp')
            ->width(48)->height(48);

        $this->addMediaConversion('avatar-card')
            ->queued()
            ->format('webp')
            ->width(200)->height(200);

        $this->addMediaConversion('avatar-hero')
            ->queued()
            ->format('webp')
            ->width(800)->height(800);
    }
}
