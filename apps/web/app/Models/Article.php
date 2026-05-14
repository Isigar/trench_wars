<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sitemap\Contracts\Sitemapable;
use Spatie\Sitemap\Tags\Url;
use Spatie\Translatable\HasTranslations;

/**
 * Source: .planning/phases/07-cms/07-03-PLAN.md task 1 + 07-RESEARCH.md Pattern 1, 2, 7.
 *
 * Phase 7 root editorial entity. Combines:
 *   - HasUuidPrimaryKey  — UUIDv4 via pgcrypto (Phase 1 idiom)
 *   - HasTranslations on title/excerpt/body — JSONB locale-keyed (D-013)
 *   - InteractsWithMedia + HasMedia — Spatie MediaLibrary hero/og-image (Pattern 2)
 *   - LogsActivity — D-012 audit trail; useLogName('article') namespaces rows
 *   - SoftDeletes — articles retain history; observer (plan 07-06) enforces
 *     publish-once monotonicity
 *   - Sitemapable — Phase 7 plan 07-12 implements toSitemapTag(); declared here
 *     so plans 07-04..07-12 can typehint against the contract without follow-up
 *     class-modification churn
 *
 * Pattern 7 (events MorphMany): Article is the 3rd eventable_type alongside
 * GameMatch (Phase 4) + Tournament (Phase 6). Relation type is MorphMany (per plan)
 * even though the events table's events_one_per_owner UNIQUE constraint makes
 * the relation functionally one-to-one — collection-shaped Eloquent return matches
 * plan must_haves verbatim and gives downstream consumers flexibility.
 *
 * LogsActivity idiom (Phase 6 D-06-03-B + D-06-03-C verbatim continuation):
 *   logFillable() — captures full $fillable diff on every mutation
 *   logOnlyDirty() — emits only changed attributes
 *   dontLogIfAttributesChangedOnly(['updated_at']) — skips noise where only
 *     the timestamp moved (e.g. ->touch())
 *   useLogName('article') — partitions activity_log rows so plan 07-11 admin
 *     audit page can filter to article events alone
 *
 * Media conversions (Pattern 2 — Pitfall 6 mitigation):
 *   thumb (600x400, queued, responsive) — card layout on listing pages
 *   hero (1600x900, queued, responsive) — show-page banner
 *   og-image (1200x630, NON-QUEUED) — SEO first-paint; the og:image meta tag
 *     must be available the moment the publisher hits "Publish" before the
 *     Horizon worker processes thumb/hero. nonQueued() runs the conversion
 *     synchronously inline with the upload.
 *
 * Fit::Crop is the cover-crop strategy (Spatie image v3 enum) — resize-and-crop
 * to exact dimensions. The plan's <interfaces> says "Fit::Cover" which does NOT
 * exist in spatie/image; Fit::Crop is the canonical equivalent (verified via
 * vendor/spatie/image/src/Enums/Fit.php enum cases).
 */
class Article extends Model implements HasMedia, Sitemapable
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['title', 'excerpt', 'body'];

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'category_id',
        'title',
        'excerpt',
        'body',
        'status',
        'scheduled_at',
        'published_at',
        'author_user_id',
        'allow_discord_announce',
    ];

    /**
     * @return array<string, string>
     *
     * NOTE: title/excerpt/body are intentionally NOT cast — HasTranslations
     * handles the JSONB read/write loop for these fields. Adding 'array' casts
     * here would conflict with the trait (T-07-03-01 mitigation).
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'allow_discord_announce' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('article');
    }

    /**
     * 3 conversions, all bound to the 'hero' collection (the only collection
     * articles use in v1). Pattern 2 verbatim.
     *
     * Method-call order is deliberate: Conversion-specific methods
     * (performOnCollections, withResponsiveImages, nonQueued) run BEFORE the
     * ImageDriver-proxied ->fit() call. Conversion::__call() proxies to
     * Manipulations and returns self, but the class declares `@mixin ImageDriver`
     * for IDE/Larastan convenience — so PHPStan sees ->fit() returning
     * ImageDriver (not Conversion). Calling performOnCollections / nonQueued /
     * withResponsiveImages FIRST keeps the chain on the actual Conversion
     * receiver where those methods are real.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('hero')
            ->withResponsiveImages()
            ->fit(Fit::Crop, 600, 400);

        $this->addMediaConversion('hero')
            ->performOnCollections('hero')
            ->withResponsiveImages()
            ->fit(Fit::Crop, 1600, 900);

        $this->addMediaConversion('og-image')
            ->performOnCollections('hero')
            ->nonQueued()
            ->fit(Fit::Crop, 1200, 630);
    }

    /**
     * Route model binding uses slug (e.g. /news/{slug}). Mirrors Tournament + Clan.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /** @return MorphMany<Event, $this> */
    public function events(): MorphMany
    {
        return $this->morphMany(Event::class, 'eventable');
    }

    /**
     * Sitemapable contract — implementation lands in plan 07-12 alongside the
     * sitemap.xml route and feed generation. Declared here so downstream code
     * can typehint Article : Sitemapable without a class-modification round.
     *
     * @return Url|string|array<string, mixed>
     *
     * @throws \LogicException unconditionally until plan 07-12 fills the body.
     */
    public function toSitemapTag(): Url|string|array
    {
        throw new \LogicException('Sitemapable implementation lands in plan 07-12');
    }
}
