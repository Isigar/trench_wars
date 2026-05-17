---
phase: 07-cms
plan: 03
subsystem: cms-models
tags:
  - wave-2
  - models
  - eloquent
  - has-media
  - has-translations
  - logs-activity
  - polymorphic-events
  - public-article-data-dto
  - category-seeder
  - phase-7-cms
dependency-graph:
  requires:
    - .planning/phases/07-cms/07-02-SUMMARY.md          # articles + categories tables must exist
    - .planning/phases/06-tournaments-brackets/06-03-SUMMARY.md  # LogsActivity Phase 6 idiom precedent
    - .planning/phases/04-matches-manual/04-03-SUMMARY.md        # polymorphic Event/eventable_type precedent
  provides:
    - "App\\Models\\Article — HasMedia + InteractsWithMedia + HasTranslations(title/excerpt/body) + LogsActivity(useLogName=article) + SoftDeletes + Sitemapable (impl deferred 07-12)"
    - "App\\Models\\Article 3 media conversions on 'hero' collection: thumb (Fit::Crop 600x400 queued, responsive), hero (Fit::Crop 1600x900 queued, responsive), og-image (Fit::Crop 1200x630 nonQueued for SEO first-paint)"
    - "App\\Models\\Article relationships: category() BelongsTo Category, author() BelongsTo User on author_user_id (nullable), events() MorphMany Event (Pattern 7 — Article is 3rd eventable_type)"
    - "App\\Models\\Category — HasTranslations(name) + LogsActivity(useLogName=category) + SoftDeletes; articles() HasMany Article"
    - "App\\Data\\PublicArticleData DTO with #[TypeScript] attribute — shape stable for plans 07-05 + 07-09 + 07-10 + 07-12; bodyHtml='' (TODO marker for plan 07-05 tiptap_converter integration)"
    - "ArticleFactory + CategoryFactory definition() bodies replacing 07-01 Wave 0 stubs — phpstan-ignore lines stripped, canonical @extends Factory<Article>/Factory<Category> generics restored"
    - "CategorySeeder — 4 starter categories (Open Question 3 LOCKED inline): News, Match Reports, Tournament Updates, Community"
    - "DatabaseSeeder::run() registers CategorySeeder after GameSeeder"
    - "ArticleModelTest GREEN — 12 it() blocks (target was 6+); CategoryModelTest GREEN — 6 it() blocks (target was 3+); PublicArticleDataTest 4 GREEN + 1 skip marker"
  affects:
    - apps/web/app/Models/                              # +2 models
    - apps/web/app/Data/                                # +1 DTO
    - apps/web/database/factories/                      # 2 stubs → real definitions
    - apps/web/database/seeders/                        # +1 seeder + DatabaseSeeder edit
    - apps/web/tests/Feature/Models/                    # +2 GREEN test files
    - apps/web/tests/Unit/Data/                         # RED stub → 4 GREEN + 1 skip
    - apps/web/resources/js/types/api.d.ts              # +PublicArticleData via typescript-transformer
    - packages/shared-types/src/api.d.ts                # mirrored TS shape
tech-stack:
  added: []
  patterns:
    - "Spatie MediaLibrary registerMediaConversions() with method-call order
      enforced (Conversion-native methods first — performOnCollections, nonQueued,
      withResponsiveImages — then ImageDriver-proxied ->fit() last). The Conversion
      class declares `@mixin ImageDriver` so PHPStan resolves ->fit() to ImageDriver
      return; chaining Conversion methods AFTER ->fit() raises method.notFound errors.
      This is a defensive idiom every HasMedia model in Trenchwars should follow.
      Plans 07-04..07-12 inherit this pattern when authoring additional conversions."
    - "Article-as-3rd-eventable_type (Pattern 7 verbatim continuation). MorphMany
      (not MorphOne as Tournament/GameMatch use) was authored per plan must_haves;
      the events_one_per_owner DB UNIQUE makes the relation functionally one-to-one
      but the collection-shaped return type gives plan 07-12 sitemap consumers
      flexibility for batched calendar projections."
    - "Sitemapable interface declared with toSitemapTag() throwing LogicException —
      Phase 7 lands the interface contract here so plans 07-04..07-12 can typehint
      against Article : Sitemapable without follow-up class-modification churn.
      Plan 07-12 fills the body when sitemap.xml route lands."
    - "PublicArticleData DTO partial-impl pattern — DTO shape + fromModel() factory
      ship here with bodyHtml='' as a documented marker. Plan 07-05 swaps in
      tiptap_converter()->asHTML($article->getTranslation('body', $locale)). This
      isolates the DTO API (consumed by plans 07-05/09/10/12) from the converter
      dependency (only owned by plan 07-05)."
    - "Pest model test convention (Phase 5 D-05-01-C canonical): bare functions,
      no namespace, no per-file uses() — Pest.php wires TestCase + RefreshDatabase
      via uses(...)->in('Feature'). Unit tests don't get RefreshDatabase by default;
      this plan added a local `uses(RefreshDatabase::class)` to PublicArticleDataTest
      because the test creates real Eloquent rows."
key-files:
  created:
    - apps/web/app/Models/Article.php
    - apps/web/app/Models/Category.php
    - apps/web/app/Data/PublicArticleData.php
    - apps/web/database/seeders/CategorySeeder.php
    - apps/web/tests/Feature/Models/ArticleModelTest.php
    - apps/web/tests/Feature/Models/CategoryModelTest.php
  modified:
    - apps/web/database/factories/ArticleFactory.php       # Wave 0 stub → real definition()
    - apps/web/database/factories/CategoryFactory.php      # Wave 0 stub → real definition()
    - apps/web/database/seeders/DatabaseSeeder.php         # register CategorySeeder after GameSeeder
    - apps/web/tests/Unit/Data/PublicArticleDataTest.php   # RED stub → 4 GREEN + 1 skip
    - apps/web/resources/js/types/api.d.ts                 # +PublicArticleData (typescript-transformer)
    - packages/shared-types/src/api.d.ts                   # mirror of api.d.ts
decisions:
  - "D-07-03-A — Use Spatie\\Image\\Enums\\Fit::Crop (NOT Fit::Cover as the plan
    <interfaces> literally read). The Fit enum in spatie/image v3 exposes
    {Contain, Max, Fill, Stretch, Crop, FillMax} — no Cover case exists. Fit::Crop
    is the canonical cover-crop equivalent (resize-and-crop to exact dimensions),
    matching the plan's semantic intent verbatim. Verified against vendor source
    at vendor/spatie/image/src/Enums/Fit.php."
  - "D-07-03-B — Conversion method-call order is performOnCollections() → nonQueued()
    | withResponsiveImages() → fit() (Conversion-native methods FIRST, ImageDriver-
    proxied fit() LAST). The Spatie Conversion class declares `@mixin ImageDriver`
    for IDE/Larastan convenience; ->fit() returns ImageDriver to PHPStan, so chaining
    Conversion methods after fit() produces method.notFound errors. This is a
    project-wide rule for every HasMedia model going forward — captured in
    Article.php registerMediaConversions() docblock."
  - "D-07-03-C — Spatie\\Activitylog\\Models\\Concerns\\LogsActivity +
    Spatie\\Activitylog\\Support\\LogOptions are the canonical class paths in this
    codebase (Phase 4/6 idiom). The plan's <interfaces> referenced
    Spatie\\Activitylog\\Traits\\LogsActivity + Spatie\\Activitylog\\LogOptions —
    those paths exist on older library versions but are not the canonical Phase 4/6
    precedent. Following the existing precedent keeps Article/Category symmetric
    with Tournament/GameMatch/Clan/Event/MatchResult etc."
  - "D-07-03-D — Article::events() uses morphMany (collection-shaped return) per
    plan must_haves verbatim, even though events_one_per_owner DB UNIQUE makes it
    functionally one-to-one. Tournament + GameMatch use morphOne. The mismatch is
    intentional: plan must_haves chose morphMany to give plan 07-12 sitemap
    consumers flexibility for batched calendar projections."
  - "D-07-03-E — PublicArticleData::fromModel() emits bodyHtml='' as a documented
    partial-impl marker. Plan 07-05 will fill it with tiptap_converter()->asHTML.
    This isolates DTO consumers (plans 07-05/09/10/12) from the converter
    dependency. PublicArticleDataTest carries an explicit ->skip() marker for the
    verifier (line: 'Plan 07-05 wires tiptap_converter()->asHTML for the bodyHtml field')."
  - "D-07-03-F — Article::registerMediaConversions() docblock captures the
    project-wide rule: 3 conversions ALL bound to the 'hero' collection (the only
    collection articles use in v1). Plan 07-05 SpatieMediaLibraryFileUpload field
    must use ->collection('hero') matching the performOnCollections('hero') here —
    zero re-configuration of disk / collection names downstream."
metrics:
  duration: 11m 58s
  completed: 2026-05-14
  tasks: 2
  files_created: 6
  files_modified: 6
  commits: 2
---

# Phase 7 Plan 3: Wave 2 Models + Factories + CategorySeeder Summary

Phase 7 Wave 2 — Article + Category Eloquent models with the full trait stack
(HasMedia/InteractsWithMedia, HasTranslations, LogsActivity, SoftDeletes,
Sitemapable for Article) sit on top of the Wave 1 schema. Real factory bodies
replace the 07-01 Wave 0 stubs. CategorySeeder lands the 4 LOCKED starter
categories. The PublicArticleData DTO ships shape-stable to unblock plans
07-04..07-12.

## Surface Delivered

### Article model (apps/web/app/Models/Article.php)

```text
class Article extends Model implements HasMedia, Sitemapable
{
    use HasFactory<ArticleFactory>;
    use HasTranslations;        // translatable = ['title', 'excerpt', 'body']
    use HasUuidPrimaryKey;      // UUIDv4 via pgcrypto
    use InteractsWithMedia;     // 3 conversions on 'hero' collection
    use LogsActivity;           // useLogName('article')
    use SoftDeletes;            // deleted_at retained

    Relations:
      category() BelongsTo Category
      author() BelongsTo User on author_user_id (nullable)
      events() MorphMany Event (Pattern 7 — 3rd eventable_type)

    Sitemapable contract:
      toSitemapTag() throws LogicException — body lands in plan 07-12.

    Media conversions (registerMediaConversions on 'hero' collection):
      thumb    — Fit::Crop 600x400 queued + responsive
      hero     — Fit::Crop 1600x900 queued + responsive
      og-image — Fit::Crop 1200x630 NON-QUEUED (SEO first-paint, Pitfall 6)

    LogsActivity options (Phase 6 D-06-03-B + D-06-03-C verbatim):
      logFillable + logOnlyDirty + dontLogIfAttributesChangedOnly(['updated_at'])
      + useLogName('article')
}
```

### Category model (apps/web/app/Models/Category.php)

```text
class Category extends Model
{
    use HasFactory<CategoryFactory>;
    use HasTranslations;        // translatable = ['name']
    use HasUuidPrimaryKey;
    use LogsActivity;           // useLogName('category')
    use SoftDeletes;

    Relations:
      articles() HasMany Article

    LogsActivity options:
      logFillable + logOnlyDirty + dontLogIfAttributesChangedOnly(['updated_at'])
      + useLogName('category')
}
```

### PublicArticleData DTO (apps/web/app/Data/PublicArticleData.php)

```text
#[TypeScript]
final class PublicArticleData extends Spatie\LaravelData\Data
{
    public function __construct(
      public string $id, $slug, $title, $bodyHtml, $categoryName, $url,
      public ?string $excerpt, $authorName, $heroThumbUrl, $heroOgImageUrl, $publishedAt,
      public bool $allowDiscordAnnounce,
    ) {}

    public static function fromModel(Article $a): self {
      // bodyHtml='' — plan 07-05 wires tiptap_converter()->asHTML
      // heroThumbUrl + heroOgImageUrl null-safe via getFirstMediaUrl
    }
}
```

Typescript-transformer auto-emitted to `apps/web/resources/js/types/api.d.ts`
and mirrored in `packages/shared-types/src/api.d.ts` (12-field type alias). Plan
07-09 + 07-12 consume this shape from Vue + sitemap renderers respectively.

## CategorySeeder — Open Question 3 LOCKED

```sql
trenchwars=# SELECT slug, name FROM categories ORDER BY created_at;
        slug        |             name
--------------------+------------------------------
 news               | {"en": "News"}
 match-reports      | {"en": "Match Reports"}
 tournament-updates | {"en": "Tournament Updates"}
 community          | {"en": "Community"}
(4 rows)
```

4 starter categories seeded via `Category::firstOrCreate(['slug' => Str::slug($name)], ...)`.
Re-running CategorySeeder is a no-op (verified via `db:seed --class=CategorySeeder`
→ COUNT(*) unchanged at 4).

Registered in `DatabaseSeeder::run()` AFTER `GameSeeder` (correct ordering —
Phase 7 migrations land in Wave 1 timestamp 2026_05_15_1200xx after every prior phase).

## Test Surface (3 GREEN files replacing 07-01 stubs / new)

| File | Pass count | Coverage |
|------|------------|----------|
| `tests/Feature/Models/ArticleModelTest.php` (NEW) | **12 GREEN** | HasTranslations title/excerpt/body, HasMedia hero attach, BelongsTo category + nullable author, MorphMany events, LogsActivity create + update with log_name='article', slug route key, casts (bool/datetime), 3-conversion registration on 'hero', Sitemapable LogicException guard |
| `tests/Feature/Models/CategoryModelTest.php` (NEW) | **6 GREEN** | HasTranslations name, HasMany articles, UNIQUE slug, route key, LogsActivity log_name='category', SoftDeletes preserve-row |
| `tests/Unit/Data/PublicArticleDataTest.php` (Wave 0 RED → GREEN partial) | **4 GREEN + 1 skip** | DTO shape, null-safe paths, plan 07-05 skip marker for bodyHtml/tiptap_converter |

Filtered run: `docker compose exec web ./vendor/bin/pest --filter='ArticleModelTest|CategoryModelTest|PublicArticleDataTest'` → **22 passed / 1 skipped / 55 assertions / 2.24s**.

Full suite regression: **892 passed / 15 expected Wave 0 RED stubs / 1 skipped / 2798 assertions / 51s**. Phase 7 plan 07-02 baseline was 870 passed / 16 expected RED stubs → diff is +22 GREEN (this plan), −1 RED (PublicArticleDataTest converted), +1 skip marker. All 15 remaining failures are owned by future Phase 7 plans (07-04..07-12) and are tagged with their plan number in the placeholder text.

## Schema Invariants Re-Verified

```sql
trenchwars=# \d articles
  category_id            | uuid                        |           | not null |
  author_user_id         | uuid                        |           |          |
Foreign-key constraints:
    "articles_author_user_id_foreign" FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL
    "articles_category_id_foreign" FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
```

Plan verification line 246 satisfied — `author_user_id` FK has `ON DELETE SET NULL`.

## Pint + PHPStan Gates

| Gate | Files | Result |
|------|-------|--------|
| `make pint --test` | 10 task files | **PASS** (Pint auto-fixed fully_qualified_strict_types on test files during authoring; final --test exits clean) |
| `make phpstan` | 10 task files | **[OK] No errors** (Larastan L8) |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug, Documentation drift] Plan's `Fit::Cover` does not exist; used `Fit::Crop` instead.**
- **Found during:** Task 1 first PHPStan pass on Article.php.
- **Issue:** The plan's `<interfaces>` block specifies `Fit::Cover` for all 3 media conversions. The `Spatie\Image\Enums\Fit` enum (v3) defines cases `{Contain, Max, Fill, Stretch, Crop, FillMax}` — no `Cover` case exists. `Fit::Cover` would be a PHP fatal at first call.
- **Fix:** Use `Fit::Crop` (the canonical cover-crop equivalent — resize-and-crop to exact dimensions, matching the plan's semantic intent). Verified against `vendor/spatie/image/src/Enums/Fit.php`. Documented in D-07-03-A and in the Article docblock.
- **Files modified:** `apps/web/app/Models/Article.php`
- **Commit:** `bee111c`

**2. [Rule 3 — Blocking issue, PHPStan resolution] Conversion method-call order to satisfy PHPStan L8.**
- **Found during:** Task 1 PHPStan run reported `Call to an undefined method Spatie\Image\Drivers\ImageDriver::withResponsiveImages()` and `::nonQueued()`.
- **Issue:** `Spatie\MediaLibrary\Conversions\Conversion` declares `@mixin ImageDriver` for IDE/Larastan convenience. After `->fit()` (an ImageDriver-proxied call via Conversion's `__call`), PHPStan tracks the receiver as ImageDriver instead of Conversion. Subsequent calls to Conversion-native methods (`withResponsiveImages`, `nonQueued`, `performOnCollections`) raise `method.notFound`.
- **Fix:** Reorder the chain so Conversion-native methods come BEFORE `->fit()`. Final shape: `addMediaConversion('thumb')->performOnCollections('hero')->withResponsiveImages()->fit(Fit::Crop, 600, 400)`. Captured as D-07-03-B and embedded in the Article docblock as a project-wide rule for every HasMedia model going forward.
- **Files modified:** `apps/web/app/Models/Article.php`
- **Commit:** `bee111c`

**3. [Rule 3 — Blocking issue, PHPStan iterable type] toSitemapTag() return type needed `array<string, mixed>` shape.**
- **Found during:** Task 1 PHPStan run.
- **Issue:** `Sitemapable::toSitemapTag()` declares return `Url|string|array` — PHPStan L8 flags raw `array` without value-type annotation. The plan's interface declares it but doesn't include the docblock annotation.
- **Fix:** Add `@return Url|string|array<string, mixed>` to the docblock. The body still throws LogicException; the annotation is forward-compatible with plan 07-12's eventual implementation.
- **Files modified:** `apps/web/app/Models/Article.php`
- **Commit:** `bee111c`

**4. [Rule 1 — Bug] PublicArticleData::fromModel() initially referenced $article->author?->name; User has username not name.**
- **Found during:** Task 2 authoring (caught before pest run via User model inspection).
- **Issue:** Plan `<interfaces>` line `public string $authorName` doesn't specify the source attribute. `User::$name` does not exist on this schema — Discord username is canonical identity (D-002), exposed as `$user->username`.
- **Fix:** Changed `$article->author?->name` → `$article->author?->username` in `PublicArticleData::fromModel()`. Documented in the DTO docblock.
- **Files modified:** `apps/web/app/Data/PublicArticleData.php`
- **Commit:** `426ff4a`

**5. [Rule 1 — Bug] First ArticleModelTest 3-conversion smoke test crashed on Arr::exists(null array).**
- **Found during:** Task 2 first pest run; `it registers 3 media conversions` failed with `TypeError: array_key_exists(): Argument #2 ($array) must be of type array, null given`.
- **Issue:** Initial test invoked `app(ConversionCollection::class)::createForMedia(new Media([...]))`. ConversionCollection's resolver does `Arr::get(Relation::morphMap(), $media->model_type, $media->model_type)` — when no morphMap entry exists (our codebase has no morph map registered yet for Article), it falls through to a null path that crashes inside Arr.
- **Fix:** Bypass ConversionCollection's morphMap lookup entirely. Instead invoke `$article->registerAllMediaConversions()` (InteractsWithMedia trait method) which populates `$article->mediaConversions` directly, then iterate that public array. Used `$conversion->shouldBePerformedOn('hero')` as the per-conversion assertion. Simpler, more robust, and exercises the actual code path that runtime uses.
- **Files modified:** `apps/web/tests/Feature/Models/ArticleModelTest.php`
- **Commit:** `426ff4a`

### Architectural changes (Rule 4)

None.

### Auth gates encountered

None.

## Threat Model Status

| Threat ID | Status |
|-----------|--------|
| T-07-03-01 (body Tiptap-JSON vs HasTranslations) | **mitigated** — `body` is in `$translatable` array (HasTranslations handles JSONB); explicitly NOT cast (would conflict with trait). Round-trip verified in `ArticleModelTest::it round-trips translatable excerpt + body via HasTranslations` |
| T-07-03-02 (Author soft-delete cascading) | **accepted** — `author_user_id` FK is `ON DELETE SET NULL` (verified via `\d articles`); PublicArticleData emits null authorName when author is null (verified via `it emits null authorName when author_user_id is null`) |
| T-07-03-03 (Polymorphic events morph attack) | **mitigated (partial)** — Article registers `morphMany(Event::class, 'eventable')` returning the polymorphic relation. Phase-4 morph map enforcement (App\Providers morph alias) still pending — Article uses FQN-typed `eventable_type` for now. Plan 07-06 ArticleObserver writes/updates the event row; the DB UNIQUE `events_one_per_owner` is defence-in-depth. |
| T-07-03-04 (Raw SQL bypass of LogsActivity) | **accepted** — Activity log governs Eloquent-driven mutations only; documented in CLAUDE.md §6 |
| T-07-03-05 (Image upload DoS) | **mitigated** — og-image conversion is `->nonQueued()` (immediate, single image); thumb + hero are queued. Horizon worker config from Phase 5 caps queue throughput. Verified by reading `$article->mediaConversions` in test: og-image conversion's `performOnQueue` flag is false. |
| T-07-03-06 (Category seeder in production) | **accepted** — Categories are public content; the 4 seeded names are operator-confirmed at phase verification time |

## Open Question Resolutions

| OQ | Resolution |
|----|------------|
| OQ-3 (starter category set) | **LOCKED inline (per plan):** 4 categories seeded — News, Match Reports, Tournament Updates, Community. Slugs are `news`, `match-reports`, `tournament-updates`, `community`. EN-only names; per-locale category names are deferred to Phase 9 admin UI work. |

## Plan ::booted() observation

`Article::booted()` is NOT defined in this plan (per plan must_haves line 35:
"ArticleObserver placeholder NOT registered yet — plan 07-06 ships real observer
+ registration via Article::observed; Article::booted() empty in this plan").

The test suite passes cleanly without any observer registration:
- The `it writes an activity_log row on Article::create` test passes because the
  `LogsActivity` trait's own boot logic registers activity logging on Model
  lifecycle events (independent of any observer).
- The `it exposes events() MorphMany` test passes because the relation is a
  pure Eloquent method, not observer-driven.
- The `it relates to a category via BelongsTo` test does not require an observer.

Plan 07-06 will add `protected static function booted() { static::observe(ArticleObserver::class); }`
when ArticleObserver lands.

## Generic Factory Shape

Both factories now carry the canonical generic annotation (the 07-01 phpstan-ignore
lines are gone):

```php
/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory { protected $model = Article::class; ... }

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory { protected $model = Category::class; ... }
```

PHPStan L8 resolves `Article::factory()->create([...])` to `Article` (not `Model`)
in downstream callers. The Wave 0 stub's per-line `@phpstan-ignore missingType.generics`
and `@phpstan-ignore property.defaultValue` annotations are removed.

## Known Stubs

The PublicArticleDataTest carries an explicit `->skip()` marker:

```php
it('TODO: bodyHtml integration with tiptap_converter (plan 07-05)', function (): void {
    // ...
})->skip('Plan 07-05 wires tiptap_converter()->asHTML for the bodyHtml field');
```

`PublicArticleData::fromModel()` emits `bodyHtml=''` with a `TODO plan 07-05` comment.
This is an INTENTIONAL stub captured by D-07-03-E — the DTO API stabilises here
so 4 downstream plans (07-05, 07-09, 07-10, 07-12) can typehint against it;
the body-HTML resolution depends on tiptap_converter which is plan 07-05's
single responsibility.

## Commit Trail

| Task | Commit | Files |
|------|--------|-------|
| 1: Article + Category models + 2 real factories | `bee111c` | 4 (2 created + 2 modified) |
| 2: CategorySeeder + 3 GREEN tests + PublicArticleData DTO + auto-gen TS types | `426ff4a` | 8 (4 created + 4 modified) |

## Self-Check

- [x] `apps/web/app/Models/Article.php` — FOUND
- [x] `apps/web/app/Models/Category.php` — FOUND
- [x] `apps/web/app/Data/PublicArticleData.php` — FOUND
- [x] `apps/web/database/factories/ArticleFactory.php` — FOUND (modified)
- [x] `apps/web/database/factories/CategoryFactory.php` — FOUND (modified)
- [x] `apps/web/database/seeders/CategorySeeder.php` — FOUND
- [x] `apps/web/database/seeders/DatabaseSeeder.php` — FOUND (modified)
- [x] `apps/web/tests/Feature/Models/ArticleModelTest.php` — FOUND
- [x] `apps/web/tests/Feature/Models/CategoryModelTest.php` — FOUND
- [x] `apps/web/tests/Unit/Data/PublicArticleDataTest.php` — FOUND (modified)
- [x] commit `bee111c` — FOUND in git log
- [x] commit `426ff4a` — FOUND in git log

## Self-Check: PASSED
