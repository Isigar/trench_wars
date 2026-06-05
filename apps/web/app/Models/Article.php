<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use App\Observers\ArticleObserver;
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

    /**
     * Phase 7 plan 07-06 — model-level observer registration (D-06-08-A
     * precedent). Eloquent registers Observers either via this static hook or
     * via Service Provider boot(); the model-level path keeps the relationship
     * colocated with the model and survives package-level rediscovery.
     */
    protected static function booted(): void
    {
        static::observe(ArticleObserver::class);
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
     * Phase 7 Pattern 2 (thumb/hero/og-image) + Phase 9 plan 09-09 Pattern 5
     * (cover-thumb/cover-card/cover-hero WebP trio for SC-4 image perf).
     *
     * The two trios coexist intentionally:
     *
     *  Phase 7 (compat-preserved, ->format('webp') applied to keep
     *  ArticleSummaryData::heroThumbUrl + PublicArticleData::heroOgImageUrl
     *  emitters working without DTO churn):
     *    - thumb     600x400 — used by ArticleCard.vue (heroThumbUrl) + SearchResultData.
     *    - hero      1600x900 — extended responsive set, full-width banner.
     *    - og-image  1200x630 NONQUEUED — kept as PNG/JPEG (no ->format) so social
     *                media scrapers (Discord/Twitter/Facebook) get a universally
     *                supported MIME — Open Question 1 LOCKED is WebP-only for the
     *                public web rendering surface, but social scrapers operate
     *                outside that browser-WebP-support window (T-09-09-04 accept).
     *
     *  Phase 9 plan 09-09 (NEW — WebP only, queued, no responsive variants):
     *    - cover-thumb 200x120 — minimal banner-style row item.
     *    - cover-card  600x400 — equivalent to phase-7 thumb but WebP-only.
     *    - cover-hero  1200x630 — OpenGraph optimal dimensions; consumed by the
     *                  WebP-aware <ArticleCover variant="hero"> renderer.
     *
     * Method-call order is deliberate (per phase-7 docblock): Conversion-specific
     * methods (performOnCollections, withResponsiveImages, nonQueued, format) run
     * BEFORE the ImageDriver-proxied ->fit() / ->width()/->height() calls so the
     * chain stays on the Conversion receiver.
     *
     * Open Question 1 LOCKED — WebP only for the cover-* trio (no JPEG fallback v1).
     * Open Question 6 LOCKED — existing Phase 7 article media is regenerated via
     * trenchwars:media:regenerate-webp (one-time deploy-step).
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Phase 7 trio — extended with ->format('webp') on thumb + hero (Open
        // Question 1 LOCKED for public web rendering). og-image stays original
        // format for social scraper compatibility.
        //
        // Chain order: performOnCollections / withResponsiveImages / format /
        // nonQueued are Conversion-receiver methods and must come BEFORE the
        // ImageDriver-proxied ->fit() — `format` returns Conversion via
        // __call/__invoke proxy, but Larastan/PHPStan sees the @mixin ImageDriver
        // type after ->format, which then refuses ->withResponsiveImages. Calling
        // withResponsiveImages BEFORE format keeps the chain on the Conversion
        // receiver where withResponsiveImages is a real method.
        $this->addMediaConversion('thumb')
            ->performOnCollections('hero')
            ->withResponsiveImages()
            ->format('webp')
            ->fit(Fit::Crop, 600, 400);

        $this->addMediaConversion('hero')
            ->performOnCollections('hero')
            ->withResponsiveImages()
            ->format('webp')
            ->fit(Fit::Crop, 1600, 900);

        $this->addMediaConversion('og-image')
            ->performOnCollections('hero')
            ->nonQueued()
            ->fit(Fit::Crop, 1200, 630);

        // Phase 9 plan 09-09 trio — WebP-only, queued, no responsive variants.
        // Naming matches plan must_haves.truths verbatim (cover-thumb / cover-card
        // / cover-hero) so the WebP-aware ArticleCover.vue consumes them by name.
        // Chain order: performOnCollections / queued / format FIRST (Conversion
        // receiver), width / height SECOND (ImageDriver-proxied) — same idiom
        // as the Phase 7 trio above.
        $this->addMediaConversion('cover-thumb')
            ->performOnCollections('hero')
            ->queued()
            ->format('webp')
            ->width(200)->height(120);

        $this->addMediaConversion('cover-card')
            ->performOnCollections('hero')
            ->queued()
            ->format('webp')
            ->width(600)->height(400);

        $this->addMediaConversion('cover-hero')
            ->performOnCollections('hero')
            ->queued()
            ->format('webp')
            ->width(1200)->height(630);
    }

    /**
     * Route model binding uses slug (e.g. /blog/{slug}). Mirrors Tournament + Clan.
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
     * Sitemapable contract — plan 07-12 implementation (replaces the 07-03 stub).
     *
     * Per 07-RESEARCH Pattern 6: route('blog.show', $slug) + updated_at lastmod +
     * WEEKLY changefreq + 0.7 priority. The SitemapGenerateCommand filters to
     * status='published' BEFORE feeding the collection to ->add(), so this method
     * never serialises a draft article (T-07-12-02 mitigation defence-in-depth).
     *
     * @return Url|string|array<string, mixed>
     */
    public function toSitemapTag(): Url|string|array
    {
        return Url::create(route('blog.show', $this->slug))
            ->setLastModificationDate($this->updated_at ?? $this->freshTimestamp())
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
            ->setPriority(0.7);
    }
}
