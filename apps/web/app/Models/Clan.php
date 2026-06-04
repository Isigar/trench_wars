<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\ClanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sitemap\Contracts\Sitemapable;
use Spatie\Sitemap\Tags\Url;
use Spatie\Translatable\HasTranslations;

/**
 * Source: .docs/05-database-schema.md § clans.
 *
 * `description` is a JSONB locale-keyed column managed by spatie/laravel-translatable.
 * `discord_role_id` is NOT in $fillable for any My-Clan-facing route (T-02-02-01 mitigation).
 *
 * Phase 9 plan 09-09 (Wave 6 — WebP image variants): InteractsWithMedia trait
 * registers three avatar conversions via Spatie medialibrary (avatar-thumb 48x48,
 * avatar-card 200x200, avatar-hero 800x800). All conversions emit WebP per SC-4
 * (image weight reduction 25-35%) and are queued via Horizon. Open Question 1
 * LOCKED — WebP only, no JPEG fallback in v1 (browser support >99%; revisit if
 * monitoring shows >0.5% failure rate post-launch).
 */
class Clan extends Model implements HasMedia, Sitemapable
{
    /** @use HasFactory<ClanFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['description'];

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'tag',
        'name',
        'description',
        'country_code',
        'owner_user_id',
        'status',
        'accepts_applications',
        'elo_rating',
        'elo_matches_count',
        'discord_role_id',
        'discord_announce_channel_id',
    ];

    /**
     * Attribute casts.
     *
     * accepts_applications is cast to boolean so callers get bool not the
     * Postgres '1'/'0' string representation.  Plan 10-02 ClanApplicationService::apply()
     * checks $clan->accepts_applications directly.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepts_applications' => 'boolean',
            'elo_rating' => 'integer',
            'elo_matches_count' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "Clan {$event}");
    }

    /**
     * Route model binding uses slug (e.g. /clans/{slug}).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsToMany<ClanTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ClanTag::class, 'clan_clan_tag');
    }

    /** @return HasMany<ClanMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(ClanMembership::class);
    }

    /** @return HasMany<ClanMembership, $this> */
    public function activeMembers(): HasMany
    {
        return $this->hasMany(ClanMembership::class)->whereNull('left_at');
    }

    /** @return HasMany<ClanInvite, $this> */
    public function invites(): HasMany
    {
        return $this->hasMany(ClanInvite::class);
    }

    /** @return HasMany<ClanApplication, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(ClanApplication::class);
    }

    /**
     * Phase 9 plan 09-09 task 1 — WebP avatar conversion trio.
     *
     * `avatar-thumb` 48x48  — micro avatar (mention rows, search dropdown).
     * `avatar-card`  200x200 — clan card / directory index (most common surface).
     * `avatar-hero`  800x800 — clan show-page hero block.
     *
     * Each conversion is ->queued() so Horizon does the work async (upload returns
     * immediately). spatie/image-optimizer runs by default — we do NOT call
     * ->nonOptimized() (RESEARCH Pattern 5 + Pitfall 6 verified).
     *
     * Open Question 1 LOCKED — WebP only, no <picture> + JPEG fallback in v1.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Chain order: Conversion-specific methods (->queued, ->format) FIRST,
        // then ImageDriver-proxied dimension methods (->width / ->height). Same
        // idiom as Article::registerMediaConversions — keeps the receiver chain
        // on the Conversion class for PHPStan/Larastan (the @mixin ImageDriver
        // proxy returns ImageDriver, not Conversion, after ->width() — so
        // ->queued() called AFTER ->width() resolves against ImageDriver and
        // raises method.notFound).
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

    /**
     * Sitemapable contract — plan 07-12 (Pattern 6 from 07-RESEARCH).
     *
     * Clans use MONTHLY changefreq + 0.5 priority — public surface but
     * mutates rarely (logo / description / roster edits, none of which
     * affect crawl-worthiness within a day).
     *
     * @return Url|string|array<string, mixed>
     */
    public function toSitemapTag(): Url|string|array
    {
        return Url::create(route('clans.show', $this->slug))
            ->setLastModificationDate($this->updated_at ?? $this->freshTimestamp())
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            ->setPriority(0.5);
    }
}
