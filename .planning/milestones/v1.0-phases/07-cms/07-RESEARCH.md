# Phase 7: CMS — Research

**Researched:** 2026-05-14
**Domain:** Editorial CMS layered on the existing Filament + Inertia + Postgres stack — articles, categories, public calendar, FTS, SSR, sitemap/meta, Discord publish announce
**Confidence:** HIGH (Context7 hits across all five focus areas; library versions cross-checked against packagist + npm registries; existing repo precedents read for medialibrary-equivalent file shape, observer→outbox pattern, Event polymorphic table, role seeding)

## Summary

Phase 7 is the editorial surface — Article + Category Filament resources, a translatable rich-text body (Tiptap stored as JSON), a polymorphic hero image (spatie/laravel-medialibrary v11), Postgres-FTS search across articles+clans+players, a public calendar page that joins Phase 4 GameMatch Events + Phase 6 Tournament Events + new editorial Event rows, a Laravel scheduler command that promotes scheduled articles to published, a server-side markdown/Tiptap-JSON-to-HTML renderer, SSR turned on in production for first paint, a sitemap.xml emitter, and an article-publish Discord announce reusing the Phase 5 `discord_outbound_messages` outbox.

Phase 7 introduces five new third-party deps (`spatie/laravel-medialibrary` `^11.22`, `awcodes/filament-tiptap-editor` `^3.5`, `ueberdosis/tiptap-php` `^2.1`, `spatie/laravel-sitemap` `^8.1`, plus npm `markdown-it` `^14.1` only if owner authors raw markdown — TipTap-JSON-via-`tiptap_converter` is the dominant path so markdown-it is a v2 option, not a v1 requirement). All five are first-party Spatie / Filament-community packages with matching version compatibility against Laravel 12 / PHP 8.4 (verified via packagist `/p2/{name}.json` 2026-05-14).

The Phase 4 `events` table (polymorphic, JSONB title, observer-driven population, unique (eventable_type, eventable_id)) is already the calendar substrate. Phase 7 adds a second polymorphic owner — `App\Models\Article` with `eventable=true` for editorial events (workshops, AMA windows, content launches) — without modifying the table schema. Phase 6 already proved the pattern (Tournament + GameMatch both project to Events).

**Primary recommendation:** Install spatie/laravel-medialibrary + awcodes/filament-tiptap-editor + ueberdosis/tiptap-php + spatie/laravel-sitemap. Store TipTap content as **JSON** (NOT HTML) using `TiptapOutput::Json` so custom blocks and merge-tags remain workable; render to HTML server-side at request time via `tiptap_converter()->asHTML($article->body)`. Use Postgres GIN indexes on three tsvector columns (one per indexed table: articles, clans, players) populated by `tsvector_update_trigger` triggers (NOT generated columns — `to_tsvector(jsonb)` is non-immutable without an explicit `'simple'` config + jsonb_path expression, which is verbose). Run a single `SearchService` that issues three parameter-bound `to_tsquery` queries and merges results in PHP — UNION-in-SQL adds little since the three tables have different columns and PlayerPrivacyGate must filter rows in PHP regardless. Enable Inertia v2 SSR via an existing-service worker pattern: add `php artisan inertia:start-ssr` to the Horizon-style `worker` service (or split a 6th `ssr` service); set `INERTIA_SSR_ENABLED=true` in production env. Discord publish announce extends the existing `discord_outbound_messages.message_type` CHECK with `article_announce` using the canonical Postgres DROP+ADD migration idiom (Phase 5/6 precedent).

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|--------------|----------------|-----------|
| Article authoring (Tiptap editor) | API / Backend (Filament + Livewire) | — | All editing happens inside the Filament admin panel; Tiptap field is Livewire-driven server-rendered with progressive JS enhancement |
| Hero image upload + resize | API / Backend (Spatie medialibrary) | Database / Storage | Conversions queued by laravel-medialibrary; binary stored on disk (Railway volume or S3 in prod) |
| Article body persistence | Database / Storage | — | JSONB column storing TipTap document; translatable via spatie/laravel-translatable inner key |
| Body → HTML conversion | Frontend Server (Laravel controller) | — | Server-side render at request time via `tiptap_converter()`; SSR-safe, no client JS needed; SEO-friendly |
| Public blog listing + detail | Frontend Server (Inertia + Vue) | Browser / Client | Server-side first paint via Inertia v2 SSR; Vue hydrates for nav (link prefetch only) |
| Calendar view (/events) | Frontend Server (Inertia + Vue + FullCalendar) | API / Backend | Server-renders the chrome; JSON feed endpoint serves Event rows; client mounts FullCalendar |
| FTS search (/search) | API / Backend (Postgres) | Frontend Server (Inertia) | Postgres tsvector + GIN — query path stays in SQL; aggregate + privacy-filter in PHP |
| Sitemap.xml emission | API / Backend (Laravel scheduler) | CDN / Static | spatie/laravel-sitemap generates XML; written to `public/sitemap.xml` daily by scheduler |
| Meta tags (og:image, twitter:card) | Frontend Server (Inertia Head) | — | Must be SSR-rendered to be visible to social-media crawlers — client-side `<Head>` mutations are invisible to crawlers |
| Auto-publish (Draft → Scheduled → Published) | API / Backend (Laravel scheduler) | — | `schedule:run` cron triggers `articles:publish-scheduled` artisan command every minute |
| Discord article-publish announce | API / Backend (DB outbox + Phase 5 bot worker) | — | ArticleObserver enqueues row in `discord_outbound_messages`; reuses Phase 5 polling worker → bot dispatch chain |

## Standard Stack

### Core (NEW packages this phase)

| Library | Version | Purpose | Why Standard | Source |
|---------|---------|---------|--------------|--------|
| `spatie/laravel-medialibrary` | `^11.22` | Hero image storage + conversions (thumb / hero / og-image) | [VERIFIED: packagist 11.22.1 published 2026-05-04] — first-party Spatie; Filament v3 ships `SpatieMediaLibraryFileUpload` form component upstream; replaces hand-rolled S3 uploads | packagist `/p2/spatie/laravel-medialibrary.json` |
| `awcodes/filament-tiptap-editor` | `^3.5` | WYSIWYG editor inside Filament Article form | [VERIFIED: packagist 3.5.16 published 2025-11-13; tagged for Filament v3 — separate 4.x branch tracks Filament v4] — highest-reputation Filament-community Tiptap binding (`High` source reputation, 142 snippets, benchmark 87.3) | Context7 `/awcodes/filament-tiptap-editor`; packagist |
| `ueberdosis/tiptap-php` | `^2.1` | Server-side Tiptap JSON ↔ HTML conversion | [VERIFIED: packagist 2.1.0 published 2026-01-10] — first-party from Tiptap maintainers; underpins `tiptap_converter()` helper exposed by filament-tiptap-editor | packagist `/p2/ueberdosis/tiptap-php.json` |
| `spatie/laravel-sitemap` | `^8.1` | sitemap.xml generation | [VERIFIED: packagist 8.1.0 published 2026-03-12] — first-party Spatie; `Sitemapable` interface integrates one-line with each model | packagist `/p2/spatie/laravel-sitemap.json` |

### Supporting (NEW)

| Library | Version | Purpose | When to Use | Source |
|---------|---------|---------|-------------|--------|
| `@fullcalendar/vue3` + `@fullcalendar/daygrid` + `@fullcalendar/timegrid` + `@fullcalendar/interaction` | `^6.1.20` | Public calendar UI at `/events` | Reach for it; building month/week/day with hand-rolled SVG/CSS at this granularity is a 2-week black hole | [VERIFIED: npm @fullcalendar/vue3@6.1.20]; Context7 `/fullcalendar/fullcalendar-docs` |
| `markdown-it` | `^14.1` | OPTIONAL: only if owner authors raw markdown | TipTap-JSON-via-`tiptap_converter` covers 95% of needs; markdown-it is a v2 secondary path | [VERIFIED: npm markdown-it@14.1.1] |
| `markdown-it`-equivalent server-side via `tiptap_converter()` | (bundled in filament-tiptap-editor) | Render TipTap JSON → HTML in Blade/controller layer | Phase 7 default render path | filament-tiptap-editor docs |

### Already Installed (REUSE)

| Library | Version | Reused For | Source |
|---------|---------|------------|--------|
| `inertiajs/inertia-laravel` | `^2.0` | SSR enable in prod (already scaffolded, flipped off in Phase 1 plan 01-06 — D-021) | `apps/web/config/inertia.php` |
| `@inertiajs/vue3` + `@inertiajs/vue3/server` | `^2.0` | SSR client + server entry | `apps/web/resources/js/ssr.ts` already exists |
| `spatie/laravel-translatable` | `^6.14` | Article title/excerpt/body JSONB i18n | D-013, Phase 2/3/4 precedent |
| `spatie/laravel-data` | `^4.22` | PublicArticleData / ArticleSummaryData DTOs | D-020 + Phase 6 PublicTournamentData precedent |
| `spatie/laravel-activitylog` | `^5.0` | LogsActivity on Article + Category | D-012 + Phase 6 Tournament precedent |
| `spatie/laravel-permission` | `^7.4` | `cms-editor` role (already seeded in `PermissionSeeder` as placeholder — `Role::findOrCreate('cms-editor', 'web')` shipped in plan 01-11) | D-018 / plan 01-11 |
| `filament/filament` | `^3.3` | ArticleResource / CategoryResource | D-012 |
| Phase 5 `discord_outbound_messages` outbox | (in-tree) | `article_announce` outbound kind | plans 05-02 + 06-08 + 06-10 migration idiom |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `awcodes/filament-tiptap-editor` | Filament v3 built-in `RichEditor` | Built-in is Trix-based, limited extensibility, no merge-tags, no custom blocks; Tiptap is the modern standard. [ASSUMED] Filament 3.3's RichEditor is Trix-based — Context7 didn't directly verify; if executor finds the v3.3 RichEditor has improved to ProseMirror, the tradeoff may flip, but awcodes/filament-tiptap-editor still wins on custom block extensibility. |
| `spatie/laravel-medialibrary` v11 | Hand-rolled S3 upload + ImageMagick CLI | Medialibrary handles conversions, responsive images, polymorphic associations, file replacement, and integrates with Filament's `SpatieMediaLibraryFileUpload` component — building this is a 3-week minimum |
| `markdown-it` (JS render) | `league/commonmark` (PHP render) | [VERIFIED: packagist league/commonmark 2.8.2] — both are CommonMark-compliant; tiptap_converter()->asHTML already handles the dominant TipTap-JSON path so neither markdown-it nor commonmark is required for v1. If a "raw markdown body" mode lands later, prefer league/commonmark (server-side, no extra Node round-trip) |
| `@fullcalendar/vue3` | Hand-rolled month grid in Vue | Building accessible month/week/day with timezone awareness, range selection, event rendering, recurring events is a 2-week investment; FullCalendar's Vue 3 wrapper is mature and MIT |
| Postgres FTS | Meilisearch | OUT OF SCOPE — REQUIREMENTS.md line 51 + CON-cms-search explicitly defers Meilisearch to v2 |
| Polymorphic `events` table (Phase 4) | Separate `editorial_events` table | The existing `events` table is already polymorphic with two owners (GameMatch + Tournament). Adding Article as a third morph subject is one factory + one observer; a separate table fragments the calendar feed. |

**Installation:**

```bash
docker compose exec web composer require \
  spatie/laravel-medialibrary:^11.22 \
  awcodes/filament-tiptap-editor:^3.5 \
  ueberdosis/tiptap-php:^2.1 \
  spatie/laravel-sitemap:^8.1

# Publish medialibrary migration + config
docker compose exec web php artisan vendor:publish \
  --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" \
  --tag="medialibrary-migrations"
docker compose exec web php artisan vendor:publish \
  --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" \
  --tag="medialibrary-config"

# Publish filament-tiptap config (optional — has sensible defaults)
docker compose exec web php artisan vendor:publish \
  --tag="filament-tiptap-editor-config"

# Migrate the new `media` table
docker compose exec web php artisan migrate

# Front-end (FullCalendar — pinned to ^6.1.20)
docker compose exec web pnpm add \
  '@fullcalendar/core@^6.1.20' \
  '@fullcalendar/vue3@^6.1.20' \
  '@fullcalendar/daygrid@^6.1.20' \
  '@fullcalendar/timegrid@^6.1.20' \
  '@fullcalendar/interaction@^6.1.20'
```

**Version verification (all versions pulled from packagist + npm registry on 2026-05-14):**

| Package | Verified version | Published |
|---------|------------------|-----------|
| spatie/laravel-medialibrary | 11.22.1 | 2026-05-04 |
| awcodes/filament-tiptap-editor | 3.5.16 | 2025-11-13 |
| ueberdosis/tiptap-php | 2.1.0 | 2026-01-10 |
| spatie/laravel-sitemap | 8.1.0 | 2026-03-12 |
| @fullcalendar/vue3 | 6.1.20 | (current) |
| markdown-it (npm) | 14.1.1 | (current) |

## Architecture Patterns

### System Architecture Diagram

```
┌──────────────────────┐
│  cms-editor (User)   │
└──────────┬───────────┘
           │ login (D-002 Discord OAuth)
           ▼
┌──────────────────────┐       ┌──────────────────────┐
│ Filament Admin Panel │──────▶│ ArticleResource      │
│  /admin/articles     │       │  (Tiptap + Media)    │
└──────────────────────┘       └──────────┬───────────┘
                                          │ save() → ArticleObserver
                                          ▼
                              ┌────────────────────────────┐
                              │ articles (Postgres)        │
                              │   + JSONB title/excerpt/   │
                              │     body (D-013)           │
                              │   + tsvector trigger →     │
                              │     GIN index              │
                              │   + media (Spatie ML)      │
                              │   + LogsActivity (D-012)   │
                              └─────────┬──────────────────┘
                                        │
        ┌───────────────────────────────┼─────────────────────────────────┐
        │                               │                                 │
        ▼                               ▼                                 ▼
┌──────────────────┐         ┌──────────────────┐               ┌────────────────────┐
│ ArticleObserver  │         │ Schedule:        │               │ discord_outbound_  │
│  created/updated │         │  articles:       │               │  messages outbox   │
│  morphOne Event  │         │  publish-        │               │  (Phase 5 reuse)   │
│  (calendar)      │         │  scheduled       │               │  kind=article_     │
└────────┬─────────┘         │  every minute    │               │   announce         │
         │                   └────────┬─────────┘               └────────┬───────────┘
         ▼                            ▼                                  │
┌──────────────────┐         ┌──────────────────┐                        │
│ events table     │         │ articles.status  │                        │
│ (polymorphic;    │         │ draft→scheduled  │                        │
│ Phase 4 reuse)   │         │ →published       │                        │
└────────┬─────────┘         └──────────────────┘                        │
         │                                                               │
         │              ┌────────────────────────────────────────────────┘
         │              │ (Phase 5 worker poll → bot dispatch)
         │              ▼
         │   ┌─────────────────────────┐
         │   │ Discord guild channel   │
         │   │  (host clan announce)   │
         │   └─────────────────────────┘
         │
         ▼
┌────────────────────────────────────────────────────────┐
│ Public surface (Inertia v2 SSR enabled in prod)        │
├────────────────────────────────────────────────────────┤
│  GET /blog              → Articles/Index.vue           │
│  GET /blog/{slug}       → Articles/Show.vue            │
│       (body rendered server-side via                   │
│        tiptap_converter()->asHTML())                   │
│  GET /events            → Events/Index.vue             │
│  GET /events/feed.json  → JSON feed for FullCalendar   │
│  GET /search?q=…        → Search/Results.vue           │
│  GET /sitemap.xml       → spatie/laravel-sitemap       │
└──────────────────┬─────────────────────────────────────┘
                   │
                   │ (1st-paint via SSR)
                   ▼
       ┌─────────────────────────────┐
       │ Inertia ssr.ts (Node 22)    │
       │  via worker or sidecar      │
       │  service on port 13714      │
       └─────────────────────────────┘
                   │
                   ▼ (FullCalendar fetches /events/feed.json)
       ┌─────────────────────────────┐
       │ Calendar JSON aggregator    │
       │  events.eventable_type IN ( │
       │    GameMatch, Tournament,   │
       │    Article)                 │
       └─────────────────────────────┘
                   │
                   ▼ (FTS search)
       ┌────────────────────────────────────────────────┐
       │ SearchService::search($q)                      │
       │   → 3× to_tsquery('simple', $q) against        │
       │     articles.search_vector,                    │
       │     clans.search_vector,                       │
       │     players.search_vector                      │
       │   → PHP-side merge + ts_rank ordering          │
       │   → PlayerPrivacyGate filter on player results │
       └────────────────────────────────────────────────┘
```

### Recommended Project Structure

```
apps/web/
├── app/
│   ├── Models/
│   │   ├── Article.php                # implements HasMedia, Sitemapable, eventable
│   │   └── Category.php               # implements Sitemapable
│   ├── Observers/
│   │   └── ArticleObserver.php        # status flip → Event morphOne + outbound enqueue
│   ├── Services/
│   │   ├── SearchService.php          # FTS across 3 indices, ts_rank ordered
│   │   ├── CalendarFeedService.php    # aggregates Event rows → FullCalendar JSON
│   │   └── ArticlePublishService.php  # state machine: draft→scheduled→published
│   ├── Console/Commands/
│   │   ├── ArticlesPublishScheduledCommand.php  # cron every-minute target
│   │   └── SitemapGenerateCommand.php           # daily sitemap rebuild
│   ├── Filament/Resources/
│   │   ├── ArticleResource.php
│   │   ├── ArticleResource/Pages/
│   │   └── CategoryResource.php
│   ├── Http/Controllers/
│   │   ├── ArticleIndexController.php
│   │   ├── ArticleShowController.php
│   │   ├── EventsCalendarController.php
│   │   ├── EventsFeedJsonController.php
│   │   ├── SearchController.php
│   │   └── SitemapController.php
│   └── Data/
│       ├── PublicArticleData.php
│       ├── PublicCategoryData.php
│       ├── SearchResultData.php
│       └── CalendarEventData.php
├── database/migrations/
│   ├── 2026_05_15_..._create_categories_table.php
│   ├── 2026_05_15_..._create_articles_table.php
│   ├── 2026_05_15_..._add_fts_to_articles_clans_players.php
│   ├── 2026_05_15_..._publish_medialibrary_migration.php
│   └── 2026_05_15_..._extend_discord_outbound_message_types_for_article_announce.php
├── resources/js/
│   └── pages/
│       ├── Articles/{Index,Show}.vue
│       ├── Events/Index.vue
│       └── Search/Results.vue
└── routes/
    ├── web.php           # add /blog, /events, /search, /sitemap.xml routes
    └── console.php       # add schedule() entries
```

### Pattern 1: Tiptap-JSON storage + server-side render

**What:** Persist editor content as TipTap JSON (not HTML); render to HTML server-side at request time.
**When to use:** Whenever rich-text content needs (a) custom blocks like merge tags, (b) safe round-tripping for re-edits without HTML/whitespace drift, (c) AI/automation that operates on structured nodes rather than HTML strings.
**Why:** `TiptapOutput::Html` flattens nodes into raw HTML at save time — irreversible for custom blocks and lossy on whitespace. `TiptapOutput::Json` keeps the ProseMirror document tree intact; `tiptap_converter()->asHTML($article->body)` (PHP helper exposed by `awcodes/filament-tiptap-editor`, internally backed by `ueberdosis/tiptap-php`) converts at render time. Server-side render keeps the article body SSR-safe and SEO-visible without any client-side JS.

**Example (Article model + Filament field + render):**

```php
// app/Models/Article.php
use FilamentTiptapEditor\Enums\TiptapOutput;   // (only needed if mapping types)
use Spatie\Translatable\HasTranslations;

class Article extends Model implements HasMedia, Sitemapable
{
    use HasTranslations, HasUuidPrimaryKey, InteractsWithMedia, LogsActivity;

    /** @var list<string> */
    public array $translatable = ['title', 'excerpt', 'body'];

    /** @var list<string> */
    protected $fillable = [
        'slug', 'category_id', 'title', 'excerpt', 'body',
        'status', 'scheduled_at', 'published_at',
        'author_user_id', 'allow_discord_announce',
    ];

    protected function casts(): array {
        return [
            'body' => 'array',  // JSONB → array for translated-jsonb shape
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'allow_discord_announce' => 'boolean',
        ];
    }
}
```

```php
// app/Filament/Resources/ArticleResource.php (Form schema)
use FilamentTiptapEditor\TiptapEditor;
use FilamentTiptapEditor\Enums\TiptapOutput;

TiptapEditor::make('body.en')
    ->label(__('admin.article.fields.body'))
    ->profile('default')
    ->output(TiptapOutput::Json)
    ->disk('public')
    ->directory('article-media')
    ->maxContentWidth('5xl')
    ->required()
    ->columnSpanFull(),
```

```php
// Render in controller / Inertia DTO
// Source: awcodes/filament-tiptap-editor 3.x README "Rendering Content"
$html = tiptap_converter()->asHTML(
    is_string($article->body) ? json_decode($article->body, true) : $article->body
);
```

**Source:** Context7 `/awcodes/filament-tiptap-editor` "Configure Output Format" + "tiptap_converter Helper" sections.

### Pattern 2: SpatieMediaLibraryFileUpload + responsive conversions

**What:** Filament v3 form component that handles the file upload UX while medialibrary handles storage + conversions.
**When to use:** Every Article hero image; future Category banner if added.
**Why:** Drops upload, reorder, conversion, responsive image generation, and Filament-form-state binding into one component. The alternative (hand-rolling `FileUpload` + medialibrary attach hook + responsive image generation) is 100+ lines of boilerplate.

**Example (Filament Resource form + Model conversions):**

```php
// app/Filament/Resources/ArticleResource.php
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

SpatieMediaLibraryFileUpload::make('hero')
    ->collection('hero')
    ->image()
    ->imageEditor()
    ->responsiveImages()
    ->conversion('thumb')
    ->maxSize(5 * 1024)                  // KB → 5 MB
    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
    ->columnSpanFull(),
```

```php
// app/Models/Article.php registerMediaConversions
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Image\Enums\Fit;

public function registerMediaConversions(?Media $media = null): void
{
    $this->addMediaConversion('thumb')
        ->fit(Fit::Cover, 600, 400)
        ->withResponsiveImages()
        ->performOnCollections('hero');

    $this->addMediaConversion('hero')
        ->fit(Fit::Cover, 1600, 900)
        ->withResponsiveImages()
        ->performOnCollections('hero');

    $this->addMediaConversion('og-image')
        ->fit(Fit::Cover, 1200, 630)     // Twitter / Facebook OpenGraph spec
        ->nonQueued()                     // need this immediately for first-paint SEO
        ->performOnCollections('hero');
}
```

**Source:** Context7 `/spatie/laravel-medialibrary` "Defining Media Conversions" + Filament v3 `SpatieMediaLibraryFileUpload` plugin page.

### Pattern 3: Postgres tsvector + GIN + trigger-driven updates (3 tables)

**What:** Add a stored `tsvector` column to each of `articles`, `clans`, `players`; create GIN indexes; use `tsvector_update_trigger` to keep them in sync.
**When to use:** Phase 7 search bar — single endpoint, three indexed sources.
**Why:**
- Stored columns + triggers are faster than `to_tsvector(...)` expression indexes for our query mix [CITED: thoughtbot.com/blog/optimizing-full-text-search-with-postgres-tsvector-columns-and-triggers — verified via web search 2026-05-14].
- A trigger is simpler than Laravel observer-based updates and survives raw-SQL writes (seeders, Filament's bulk actions).
- Three separate GIN indexes scale linearly; UNION-in-SQL is unnecessary because PlayerPrivacyGate has to filter player rows in PHP anyway (D-018) — keep that aggregation layer in PHP.
- Use `'simple'` text-search config in round 1 — English-only at launch (D-013) and `'simple'` doesn't stem (so "fight" matches "fight" exactly, not "fighting") which is acceptable for a small editorial corpus. Switch to `'pg_catalog.english'` if owner reports relevance gaps.

**Example migration:**

```php
// database/migrations/2026_05_..._add_fts_to_articles_clans_players.php
public function up(): void
{
    // articles
    DB::statement('ALTER TABLE articles ADD COLUMN search_vector tsvector');
    DB::statement(
        "UPDATE articles SET search_vector = "
        . "to_tsvector('simple', "
        . "coalesce(title->>'en', '') || ' ' || "
        . "coalesce(excerpt->>'en', '') || ' ' || "
        . "coalesce(slug, ''))"
    );
    DB::statement('CREATE INDEX articles_search_vector_idx ON articles USING GIN (search_vector)');
    DB::statement("
        CREATE OR REPLACE FUNCTION articles_search_vector_trigger() RETURNS trigger AS $$
        BEGIN
            NEW.search_vector :=
                to_tsvector('simple',
                    coalesce(NEW.title->>'en', '') || ' ' ||
                    coalesce(NEW.excerpt->>'en', '') || ' ' ||
                    coalesce(NEW.slug, ''));
            RETURN NEW;
        END
        $$ LANGUAGE plpgsql
    ");
    DB::statement('
        CREATE TRIGGER articles_search_vector_update
        BEFORE INSERT OR UPDATE ON articles
        FOR EACH ROW EXECUTE FUNCTION articles_search_vector_trigger()
    ');

    // Repeat the trio for clans (name + description JSONB) and players (username + bio if present).
}
```

**Example SearchService usage:**

```php
// app/Services/SearchService.php (sketch)
public function search(string $q): SearchResultsCollection
{
    // Postgres-side tsquery (parameter-bound; never concat user input)
    $tsq = DB::raw("plainto_tsquery('simple', ?)");

    $articles = Article::query()
        ->whereRaw('search_vector @@ ' . $tsq->getValue(DB::connection()->getQueryGrammar()), [$q])
        ->orderByRaw("ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC", [$q])
        ->where('status', 'published')
        ->limit(20)
        ->get();

    $clans = Clan::query()
        ->whereRaw('search_vector @@ ' . $tsq->getValue(DB::connection()->getQueryGrammar()), [$q])
        ->orderByRaw("ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC", [$q])
        ->limit(20)
        ->get();

    $players = Player::query()
        ->whereRaw('search_vector @@ ' . $tsq->getValue(DB::connection()->getQueryGrammar()), [$q])
        ->orderByRaw("ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC", [$q])
        ->limit(20)
        ->get()
        ->filter(fn ($p) => app(PlayerPrivacyGate::class)->canShowInSearch($p, $viewer));   // D-018

    return SearchResultsCollection::merge($articles, $clans, $players);
}
```

**Pitfall:** Use `plainto_tsquery` for user-supplied input (it sanitises operators); `to_tsquery` is a footgun for raw user input — it throws on stray punctuation.

**Source:** [CITED: postgresql.org/docs/current/textsearch-tables.html] tsvector + GIN + trigger pattern; [CITED: depesz.com/2022/03/01/how-to-index-data-with-tsearch] indexing strategy.

### Pattern 4: Laravel Scheduler for draft→scheduled→published

**What:** Define an `articles:publish-scheduled` artisan command; schedule it `everyMinute()` in `routes/console.php`.
**When to use:** Round-1 auto-publish; works for tens of thousands of articles without queue infrastructure.
**Why:** Already running cron via Railway (D-014). Laravel scheduler picks up `routes/console.php` entries with no additional setup; the artisan command is testable in isolation; per-minute granularity matches editorial cadence.

**Example:**

```php
// app/Console/Commands/ArticlesPublishScheduledCommand.php
class ArticlesPublishScheduledCommand extends Command
{
    protected $signature = 'articles:publish-scheduled';
    protected $description = 'Promote Article status=scheduled → published when scheduled_at has passed';

    public function handle(ArticlePublishService $service): int
    {
        $count = $service->publishDue(now());
        $this->info("Published {$count} article(s).");
        return self::SUCCESS;
    }
}
```

```php
// routes/console.php
Schedule::command('articles:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();   // critical for Railway multi-replica deployment

Schedule::command('sitemap:generate')
    ->dailyAt('03:00')
    ->onOneServer();
```

```php
// app/Services/ArticlePublishService.php
public function publishDue(Carbon $now): int
{
    $count = 0;
    Article::query()
        ->where('status', 'scheduled')
        ->where('scheduled_at', '<=', $now)
        ->chunkById(100, function ($articles) use (&$count) {
            foreach ($articles as $article) {
                $article->update([
                    'status' => 'published',
                    'published_at' => now(),
                ]);
                // ArticleObserver::updated fires here → outbound + activity_log
                $count++;
            }
        });
    return $count;
}
```

**Production cron (Railway):** Already present per D-014 Railway topology; if not, add `* * * * * cd /app && php artisan schedule:run >> /dev/null 2>&1`.

**Source:** [CITED: laravel.com/docs/12.x/scheduling]; Laravel Scheduler is built into the framework.

### Pattern 5: Inertia v2 SSR enabled in production only

**What:** Run `php artisan inertia:start-ssr` as a background process; production listens on port 13714; `INERTIA_SSR_ENABLED=true` only in prod env.
**When to use:** Round-1 success criterion REQ-success-public-browse explicitly requires "SSR enabled in production for first paint on public pages."
**Why:** Public clans / players / matches / tournaments / blog / events / search pages need SEO and fast first paint. Authenticated /admin pages don't need SSR — they're behind auth, never crawled. Set `INERTIA_SSR_ENABLED=true` in prod; leave it `false` in dev to keep the dev loop fast.

**Wiring (Railway / docker-compose dev):**

Option A — extend existing `worker` service: add an additional command in the Horizon entrypoint script that backgrounds `inertia:start-ssr`. Simpler ops, single container.
Option B — split a 6th `ssr` service in docker-compose.yml + Railway: explicit, healthchecked, restartable independently. Recommended for Railway.

```yaml
# docker-compose.yml additions
ssr:
  build:
    context: .
    dockerfile: docker/web/Dockerfile
  container_name: trenchwars-ssr
  command: ["php", "artisan", "inertia:start-ssr"]
  environment:
    INERTIA_SSR_ENABLED: "true"
    INERTIA_SSR_URL: "http://0.0.0.0:13714"
  healthcheck:
    test: ["CMD", "curl", "-f", "http://127.0.0.1:13714/health"]
    interval: 10s
    timeout: 5s
    retries: 5
  depends_on:
    postgres:
      condition: service_healthy
    redis:
      condition: service_healthy
```

```php
// config/inertia.php — already scaffolded in plan 01-06; just point INERTIA_SSR_URL at the ssr service
'ssr' => [
    'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),
    'url' => env('INERTIA_SSR_URL', 'http://ssr:13714'),
    'ensure_bundle_exists' => false,
],
```

**Build step:** `pnpm build` already runs `vite build && vite build --config vite.filament.config.ts` per existing package.json. Vite's `ssr` input is already configured at `apps/web/vite.config.ts` line 19 (`ssr: 'resources/js/ssr.ts'`). Verify the SSR bundle lands at `bootstrap/ssr/ssr.mjs` after build.

**Pitfall:** Laravel Inertia ships `inertia:stop-ssr` artisan command — production deploys must call it before swapping the bundle so the new build picks up new pages [CITED: inertiajs.com/docs/v2/advanced/server-side-rendering].

**Source:** [CITED: inertiajs.com/docs/v2/advanced/server-side-rendering], [CITED: fly.io/docs/laravel/advanced-guides/using-inertia-ssr/], plan 01-06 SSR scaffold.

### Pattern 6: Sitemap + SSR Meta Tags

**What:** spatie/laravel-sitemap implements `Sitemapable` on Article/Clan/Player/Tournament; daily artisan command writes `public/sitemap.xml`. Inertia `<Head>` component sets per-page meta tags (og:image, twitter:card, description).
**When to use:** Required by SC-5 ("sitemap + meta tags emitted; `<html lang>` reflects active locale").
**Why:** Social media crawlers cannot execute JavaScript; meta tags must be in the initial HTML response (which we have once SSR is on). Sitemap drives discovery.

**Example sitemap implementation:**

```php
// app/Models/Article.php
use Spatie\Sitemap\Contracts\Sitemapable;
use Spatie\Sitemap\Tags\Url;

class Article extends Model implements HasMedia, Sitemapable
{
    public function toSitemapTag(): Url
    {
        return Url::create(route('blog.show', $this->slug))
            ->setLastModificationDate($this->updated_at)
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
            ->setPriority(0.7);
    }
}
```

```php
// app/Console/Commands/SitemapGenerateCommand.php
use Spatie\Sitemap\Sitemap;

class SitemapGenerateCommand extends Command
{
    protected $signature = 'sitemap:generate';

    public function handle(): int
    {
        Sitemap::create()
            ->add('/')
            ->add('/clans')
            ->add('/players')          // index only — individual players gated by privacy
            ->add('/matches')
            ->add('/tournaments')
            ->add('/blog')
            ->add('/events')
            ->add(Article::where('status', 'published')->get())
            ->add(Clan::all())
            ->add(Tournament::where('is_public', true)->get())
            ->writeToFile(public_path('sitemap.xml'));

        return self::SUCCESS;
    }
}
```

**Example Inertia Head usage (Vue):**

```vue
<!-- pages/Articles/Show.vue -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
defineProps<{ article: App.Data.PublicArticleData }>();
</script>

<template>
  <Head>
    <title>{{ article.title }}</title>
    <meta head-key="description" name="description" :content="article.excerpt" />
    <meta head-key="og:title" property="og:title" :content="article.title" />
    <meta head-key="og:description" property="og:description" :content="article.excerpt" />
    <meta head-key="og:image" property="og:image" :content="article.heroOgImageUrl" />
    <meta head-key="og:type" property="og:type" content="article" />
    <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
    <meta head-key="twitter:image" name="twitter:image" :content="article.heroOgImageUrl" />
  </Head>
  <article v-html="article.bodyHtml" />
</template>
```

**`head-key` is critical** — without it, navigating between pages stacks meta tags rather than replacing them. [CITED: inertiajs.com/title-and-meta]

**`<html lang>`:** Set in `apps/web/resources/views/app.blade.php` via `<html lang="{{ app()->getLocale() }}">`. Already shipped in Phase 1 (the LocaleMiddleware resolves locale before rendering). Verify it survives SSR — the SSR entrypoint should respect the locale prop passed via Inertia shared data.

**Source:** Context7 `/spatie/laravel-sitemap` "Implement Sitemapable Interface"; [CITED: inertiajs.com/title-and-meta].

### Pattern 7: Calendar — polymorphic events + FullCalendar JSON feed

**What:** Reuse the Phase 4 `events` table (already polymorphic; already owns GameMatch + Tournament rows). Add `App\Models\Article` as a third morph owner. The public `/events` page is Inertia + Vue + FullCalendar; the JSON feed is a separate JSON endpoint that FullCalendar fetches per-view.
**When to use:** SC-2 explicitly requires public visitor "views calendar at /events (month/week/day) populated by auto match/tournament events + editorial events."
**Why:**
- The polymorphic `events` table is the established calendar substrate (Phase 4 plan 04-02 + Phase 6 plan 06-10) — adding Article as a third morph owner is one observer + one factory, NOT a schema change.
- FullCalendar's JSON feed model (`events: '/events/feed.json?start=…&end=…'`) fetches per-view, lazy-loaded — fine for tens of thousands of events.
- Aggregating clientside through a single feed sidesteps the cross-table UNION dance.

**Example feed controller:**

```php
// app/Http/Controllers/EventsFeedJsonController.php
public function __invoke(Request $request): JsonResponse
{
    $validated = $request->validate([
        'start' => 'required|date',
        'end' => 'required|date|after:start',
    ]);

    $events = Event::query()
        ->where('is_public', true)
        ->whereBetween('starts_at', [$validated['start'], $validated['end']])
        ->with('eventable')                 // morphTo: GameMatch | Tournament | Article
        ->limit(1000)
        ->get()
        ->map(fn (Event $e) => CalendarEventData::fromModel($e));

    return response()->json($events);
}
```

```php
// app/Data/CalendarEventData.php
class CalendarEventData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public string $start,
        public ?string $end,
        public string $type,    // 'match' | 'tournament' | 'article'
        public string $url,     // /matches/X | /tournaments/X | /blog/X
        public string $color,   // category-coloured
    ) {}

    public static function fromModel(Event $event): self {
        $owner = $event->eventable;
        $type = match (true) {
            $owner instanceof GameMatch  => 'match',
            $owner instanceof Tournament => 'tournament',
            $owner instanceof Article    => 'article',
            default => 'other',
        };
        return new self(
            id: (string) $event->id,
            title: $event->getTranslation('title', app()->getLocale()) ?? '(untitled)',
            start: $event->starts_at->toIso8601String(),
            end: $event->ends_at?->toIso8601String(),
            type: $type,
            url: self::resolveUrl($type, $owner),
            color: self::colourFor($type),
        );
    }
}
```

**Example Vue page:**

```vue
<!-- pages/Events/Index.vue -->
<script setup lang="ts">
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import { t } from '@/composables/useT';

const calendarOptions = {
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay',
  },
  events: '/events/feed.json',
  eventClick: (info: { event: { extendedProps: { url: string } } }) => {
    window.location.assign(info.event.extendedProps.url);
  },
};
</script>
<template>
  <FullCalendar :options="calendarOptions" />
</template>
```

**Source:** Context7 `/fullcalendar/fullcalendar-docs` "Vue 3 Integration" + "JSON Feed" sections.

### Pattern 8: Discord article_announce outbound (Phase 5 reuse)

**What:** Extend `discord_outbound_messages.message_type` CHECK constraint to include `article_announce`. ArticleObserver enqueues a row when status flips draft|scheduled → published AND `allow_discord_announce=true`.
**When to use:** SC-5 requires "Discord announce on publish (per-article configurable)."
**Why:** The Phase 5 outbox + Phase 5 worker + Phase 5 bot dispatch chain is unchanged. Reuse, don't reinvent.

**Example migration (drop+add idiom — exactly mirroring plans 06-08 + 06-10):**

```php
// database/migrations/2026_05_..._extend_discord_outbound_message_types_for_article_announce.php
public function up(): void
{
    DB::statement('ALTER TABLE discord_outbound_messages DROP CONSTRAINT IF EXISTS doutmsg_message_type_chk;');
    DB::statement(
        'ALTER TABLE discord_outbound_messages ADD CONSTRAINT doutmsg_message_type_chk '
        . 'CHECK (message_type IN ('
        . "'match_announce','match_announce_update','role_sync','generic',"
        . "'bracket_result_announce',"
        . "'tournament_announce','tournament_announce_update',"
        . "'article_announce'"
        . '));'
    );
}

public function down(): void
{
    DB::statement('ALTER TABLE discord_outbound_messages DROP CONSTRAINT IF EXISTS doutmsg_message_type_chk;');
    // Restore Phase 6 state (no article_announce)
    DB::statement(
        'ALTER TABLE discord_outbound_messages ADD CONSTRAINT doutmsg_message_type_chk '
        . 'CHECK (message_type IN ('
        . "'match_announce','match_announce_update','role_sync','generic',"
        . "'bracket_result_announce',"
        . "'tournament_announce','tournament_announce_update'"
        . '));'
    );
}
```

**Example observer:**

```php
// app/Observers/ArticleObserver.php
public function updated(Article $article): void
{
    if (! $article->wasChanged('status'))         return;
    if ($article->status !== 'published')         return;
    if (! $article->allow_discord_announce)       return;
    // Avoid republish: only first transition to 'published' triggers announce
    if ($article->getOriginal('status') === 'published') return;

    DiscordOutboundMessage::create([
        'channel_id' => '',                       // resolved at dispatch time
        'message_type' => 'article_announce',
        'status' => 'pending',
        'payload' => DiscordOutboundPayloadBuilder::buildArticleAnnounce($article),
        'causer_user_id' => auth()->id(),
    ]);
}
```

**Source:** Phase 5 plan 05-02 (initial outbox shape) + Phase 6 plans 06-08 + 06-10 (CHECK extension idiom — files `2026_05_15_100500_extend_discord_outbound_message_types_for_phase_6.php` and `2026_05_15_100600_extend_discord_outbound_message_types_for_tournament_announce.php`).

### Anti-Patterns to Avoid

- **Storing TipTap content as `TiptapOutput::Html`** — irreversible for custom blocks / merge tags; lossy. Always `TiptapOutput::Json`.
- **`to_tsquery(user_input)`** — throws on stray punctuation. Use `plainto_tsquery` (sanitises) or `websearch_to_tsquery` (Google-like syntax: quotes, `OR`, `-` minus operator).
- **Computing tsvector inline in a `WHERE` clause** without an index — full table scan. Always store + index the tsvector column.
- **Generating sitemap.xml on every request** — expensive. Generate via daily artisan + write to `public/sitemap.xml`; serve via webserver (nginx static-file path; bypasses PHP entirely).
- **Adding client-side `<meta og:*>` mutations to a Vue page without `head-key`** — duplicate meta tags stack in `<head>`; SEO crawlers see them all.
- **Hardcoding meta tags in `app.blade.php`** — they're shared across all pages and don't reflect article content. Use Inertia `<Head>` with `head-key` for per-page tags.
- **Running `inertia:start-ssr` foreground in production** — single uncaught exception kills SSR; use a process supervisor (Supervisor on bare-metal, Railway's restart policy on Railway) with `inertia:stop-ssr` on deploy.
- **One sitemap with > 50,000 URLs** — sitemap spec limit. Use `SitemapIndex` to nest sitemaps when article count crosses 10,000. Not a v1 concern; flag for v2.
- **Trusting `<html v-html="article.body">` with raw editor output without sanitization** — Tiptap's permitted nodes/marks list is the sanitisation surface; configure the Filament TiptapEditor field's `->extensions()` to only allow safe nodes (no `iframe`, no `script`, no `style`). Don't sanitise post-hoc with DOMPurify on the server (jsdom + DOMPurify is expensive; pinning the editor profile is cheaper).
- **Forgetting `withoutOverlapping()` + `onOneServer()` on the publish-scheduled command** — duplicate publishes when Horizon scales horizontally, or worse, partial publishes that confuse the activity log audit trail.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| File upload + image conversion + responsive images + polymorphic association | Custom Filament FileUpload + ImageMagick CLI calls + image_responsive helper | `spatie/laravel-medialibrary` v11 + `SpatieMediaLibraryFileUpload` | 3-week black hole; medialibrary handles disk, queue, conversion, responsive, polymorphism in one trait |
| WYSIWYG editor inside Filament | Custom Livewire + textarea + JS toolbar | `awcodes/filament-tiptap-editor` ^3.5 | ProseMirror is the modern standard; custom blocks, merge tags, JSON storage are first-class |
| TipTap JSON → HTML on server | Hand-rolled node walker | `tiptap_converter()->asHTML(...)` (`ueberdosis/tiptap-php` 2.1) | First-party Tiptap maintainer code; covers every node type the Filament profile emits |
| Sitemap XML generation | Hand-rolled `view()->make('sitemap')` with foreach loops | `spatie/laravel-sitemap` ^8.1 | Handles 50K URL limit, multi-sitemap index, lastmod RFC dates, change frequency vocabulary |
| Calendar UI (month/week/day) | Hand-rolled grid in Vue | `@fullcalendar/vue3` ^6.1.20 | Accessibility, keyboard nav, drag-to-create, timezone handling, recurring events — months of work; FC is MIT |
| Full-text search | LIKE %query% across multiple columns | Postgres `tsvector + GIN + plainto_tsquery + ts_rank` | LIKE doesn't rank, doesn't stem, doesn't handle stop words, full-table scans for substrings — useless past 1K rows |
| Article status auto-publish | One-off cron-bash-script + curl-to-api | Laravel Scheduler `everyMinute()->withoutOverlapping()->onOneServer()` | Built-in, atomic, multi-replica-safe, audit-trail-aware via observer chain |
| SSR engine | Hand-rolled Express + Vue SSR + bundler integration | `@inertiajs/vue3/server` + `php artisan inertia:start-ssr` | Already scaffolded in Phase 1 plan 01-06; flip a flag, run the command |
| Discord publish announce | Direct discord.js webhook from Laravel | Phase 5 `discord_outbound_messages` outbox + `article_announce` kind | Reuse the durable outbox; idempotent; auditable; recoverable on bot failure |
| Markdown → HTML (if owner wants raw markdown) | Hand-rolled CommonMark parser | `league/commonmark` ^2.8 (PHP) | Spec-compliant; extension architecture; fast; used by Laravel's str()->markdown() helper |

**Key insight:** Phase 7 hits every category of "looks easy, secretly horrifying" — image conversion, rich text, calendar UX, full-text search, SSR, sitemap. Every one of those has a mature first-party library. Hand-rolling any of them on a 1-developer round-1 budget is a velocity disaster.

## Runtime State Inventory

> Phase 7 is **mostly greenfield** (new articles + categories + FTS columns + SSR enable + sitemap) but it adds one extension to an existing CHECK constraint and triggers tsvector backfill on existing tables. Inventory below covers the runtime-state surfaces.

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | (1) `discord_outbound_messages.message_type` CHECK constraint gets a new permitted enum value `article_announce`. (2) `articles` table created fresh. (3) `categories` table created fresh. (4) Spatie medialibrary `media` table created fresh via vendor:publish. (5) `articles.search_vector`, `clans.search_vector`, `players.search_vector` tsvector columns added + populated. | Migration: DROP+ADD CHECK constraint (Phase 5/6 idiom); UPDATE backfill on the 3 search_vector columns inside the same migration |
| Live service config | None — round 1 has zero "configured at runtime in a UI" surfaces for Phase 7. CRCON is Phase 8. n8n/Datadog not in scope (Trenchwars uses Railway built-in logs per D-014). | None |
| OS-registered state | None — Phase 7 introduces no OS-registered scheduled tasks, systemd units, launchd jobs, or process-manager registrations beyond Laravel scheduler's existing single cron entry (already present from D-014). | None |
| Secrets/env vars | (1) New env var `INERTIA_SSR_ENABLED=true` in prod (defaults to `false` in dev — D-021). (2) No new secrets — the medialibrary disk reuses the existing public disk; Tiptap has no API keys; Discord is reused from Phase 5. | Document `.env.example` shape with empty `INERTIA_SSR_ENABLED=false` |
| Build artifacts | (1) SSR bundle at `bootstrap/ssr/ssr.mjs` — already produced by existing `pnpm build`. (2) `public/sitemap.xml` — written by daily artisan command. (3) Medialibrary stores files under `apps/web/storage/app/public/articles/...` symlinked to `apps/web/public/storage/...` (Laravel default). | Verify `storage:link` runs in the deploy pipeline (Phase 1 plan 01-04 likely already does this; double-check the Dockerfile entrypoint) |

**Nothing found in OS-registered state or live service config:** explicitly verified — Phase 7 doesn't touch n8n / pm2 / Windows Task Scheduler / launchd / Datadog / Tailscale (Trenchwars uses none of these per D-014 + D-021 audit).

## Common Pitfalls

### Pitfall 1: TipTap stored as HTML loses custom blocks on round-trip

**What goes wrong:** Author edits an article with a custom merge-tag block, saves; the block flattens into raw HTML `<div data-block="merge-tag">`. Re-opening the article in Filament re-parses the HTML into Tiptap's document model and the custom block is now a generic `<div>` — Tiptap doesn't know it was a merge tag.
**Why it happens:** `TiptapOutput::Html` is destructive for non-standard nodes.
**How to avoid:** Use `TiptapOutput::Json` from day one. Migration column type is `jsonb` (Postgres), model cast is `'array'`, and the editor field declares `->output(TiptapOutput::Json)`.
**Warning signs:** Filament Resource displays editor content but the custom block toolbar item is missing on re-edit; the block renders as a generic blockquote instead of the registered preview.

### Pitfall 2: PostgreSQL `to_tsquery()` throws on user input

**What goes wrong:** User searches for "AC/DC" → Postgres throws `ERROR: syntax error in tsquery: "AC/DC"` (the `/` is a tsquery operator).
**Why it happens:** `to_tsquery` parses operators (`&`, `|`, `!`, `:`, `*`, parens). Any user input with punctuation can blow it up.
**How to avoid:** Use `plainto_tsquery` (collapses input to AND'd lexemes) or `websearch_to_tsquery` (Google-like syntax). Never concat user input directly.
**Warning signs:** Pest tests pass on clean queries; production logs show 500s on real-world searches with apostrophes, slashes, hyphens.

### Pitfall 3: tsvector column gets stale after seeder bulk-inserts

**What goes wrong:** Seeder inserts 100 articles via `Article::insert([...])` (mass insert bypass) — the model `boot()` lifecycle methods don't fire, observer doesn't run, tsvector column stays NULL.
**Why it happens:** Eloquent `insert()` is the raw SQL bypass; observers don't fire.
**How to avoid:** Drive the tsvector via a Postgres trigger (Pattern 3 example) — fires at the DB layer regardless of how rows were created. Also true for seeders that use `firstOrCreate` after a manual seed (timing race).
**Warning signs:** `SELECT title, search_vector FROM articles LIMIT 5;` returns NULLs; `WHERE search_vector @@ to_tsquery(...)` returns no results despite matching rows existing.

### Pitfall 4: Inertia `<Head>` meta tags don't replace across page nav

**What goes wrong:** Navigate from `/blog/article-a` to `/blog/article-b`; the `<head>` now has TWO `og:title` meta tags (article-a's plus article-b's). Social-media crawlers pick the first match; OG previews show the wrong article.
**Why it happens:** Inertia v2's `<Head>` stacks all tags by default; only `<title>` is replaced. Without `head-key`, you accumulate duplicates.
**How to avoid:** Every meta tag in `<Head>` must carry `head-key="<unique-key>"` (e.g., `head-key="og:title"`, `head-key="description"`). Inertia uses head-key to dedupe on navigation.
**Warning signs:** View-source on a second-pageview reveals duplicate `<meta>` tags; OG preview tools (Twitter card validator, Facebook debugger) show wrong content.

### Pitfall 5: SSR bundle out of sync with client bundle

**What goes wrong:** Deploy ships a new `app.js` (with a new page component) but the SSR bundle (`bootstrap/ssr/ssr.mjs`) still has the old page list; SSR throws `SSR page not found: ./pages/NewPage.vue` for the new page.
**Why it happens:** Vite's two-pass build produces two bundles; if the deploy pipeline rebuilds only one (or rebuilds client AFTER server start), the SSR process holds the old bundle in memory.
**How to avoid:** Deploy pipeline MUST call `php artisan inertia:stop-ssr` before deploying the new bundles, AND the build step MUST run BOTH `vite build` (client) AND `vite build --ssr resources/js/ssr.ts` (server). Process supervisor restarts the SSR service.
**Warning signs:** First navigation to a new page errors out with `SSR page not found`; refreshing the page works (SPA hydration takes over).

### Pitfall 6: medialibrary conversions queued in dev with no queue worker

**What goes wrong:** Uploaded a hero image in Filament; the form saves; the conversion never runs because `QUEUE_CONNECTION=sync` was switched to `redis` (Phase 5) but no Horizon worker is running locally; thumbnail URLs return 404 because `media.generated_conversions['thumb']` stays `false`.
**Why it happens:** Medialibrary conversions are queued by default. Phase 5's Horizon adoption means `QUEUE_CONNECTION=redis` in dev too — but the dev workflow may not have a worker running.
**How to avoid:** (a) For conversions that must be immediate (og-image, hero thumb), use `->nonQueued()` on the conversion definition. (b) Ensure the Phase 5 docker-compose `worker` service is up (`docker compose up -d worker`). (c) For local dev where the worker isn't running, set `MEDIA_LIBRARY_QUEUE_CONNECTION=sync` in `.env`.
**Warning signs:** `media.generated_conversions` JSON column shows `{"thumb": false, "hero": false}`; thumbnail URLs return 404; conversions never appear on disk.

### Pitfall 7: Sitemap with 50K+ URLs hits the spec limit

**What goes wrong:** League grows; article corpus + clan directory + player profiles exceed 50,000 URLs; sitemap.xml is rejected by Google as oversized.
**Why it happens:** Sitemap spec caps a single sitemap at 50,000 URLs / 50MB.
**How to avoid:** Use `SitemapIndex` from spatie/laravel-sitemap to nest per-type sitemaps (`/sitemap-articles.xml`, `/sitemap-clans.xml`, `/sitemap-players.xml`) under a master `/sitemap.xml` index. Not a v1 concern; defer the index split until counts approach 10,000 per type.
**Warning signs:** `sitemap.xml` file size > 10MB; Google Search Console flags "Sitemap is too large."

### Pitfall 8: SSR locale drift — `<html lang>` doesn't match content

**What goes wrong:** Inertia SSR pre-renders the page with the default locale (`en`), but the request actually carries `lang=cs` or `Accept-Language: cs`; the client hydrates with the correct locale but `<html lang>` is wrong in the SSR HTML — accessibility tools and SEO crawlers see English.
**Why it happens:** The locale resolver (cookie / query / header) runs in middleware on the PHP side; the SSR Node process needs to know the resolved locale. If the locale isn't passed via Inertia shared props AND the SSR `app.blade.php` doesn't reflect it, the SSR HTML carries the framework default.
**How to avoid:** Confirm `apps/web/resources/views/app.blade.php` uses `<html lang="{{ app()->getLocale() }}">` AND the LocaleMiddleware runs before Inertia middleware. Verify in Pest with `assertSee('lang="cs"')` after setting locale via cookie/header. Round-1 ships EN only (D-013) so this won't bite immediately, but it WILL bite the day CS/SK/PL packs land (v2).
**Warning signs:** `curl -H "Accept-Language: cs" https://prod/blog` returns `<html lang="en">` despite Czech content rendering.

### Pitfall 9: `articles_search_vector_trigger` references columns the migration hasn't created yet

**What goes wrong:** Migration creates trigger function referencing `articles.title->>'en'` but Phase 7 hasn't yet created the `articles` table. Migration fails.
**Why it happens:** Migration ordering. The FTS-trigger migration must run AFTER the create-articles migration.
**How to avoid:** Use Laravel timestamp ordering — create-articles migration timestamp `2026_05_15_100000_...`; FTS-trigger migration timestamp `2026_05_15_100100_...` (or later). Alternatively, fold the trigger into the create-articles migration as a third `DB::statement(...)` after `Schema::create(...)`.
**Warning signs:** `migrate:fresh` fails with `ERROR: relation "articles" does not exist`; on first deploy to Railway, the migration that runs second wedges and the deploy halts.

### Pitfall 10: Tiptap profile permits `iframe` or `script` — stored XSS via author

**What goes wrong:** cms-editor author pastes an iframe; the JSON document persists it; the article body renders the iframe on the public page; iframe loads an attacker site that captures session cookies.
**Why it happens:** Tiptap's default extension set is conservative but `filament-tiptap-editor`'s `'default'` profile allows YouTube embeds (iframe under the hood). Without explicit profile pinning, you trust authors not to insert anything bad.
**How to avoid:** Pin the Filament TiptapEditor profile to an explicit list — `->profile('custom')->tools(['heading', 'bold', 'italic', 'bullet-list', 'ordered-list', 'link', 'code', 'blockquote', 'media', 'horizontal-rule'])` — drop `oembed` / `youtube` for v1. Test with Pest: `expect($html)->not->toContain('<iframe');` `expect($html)->not->toContain('<script');`.
**Warning signs:** Manual smoke reveals iframe/script tags in rendered article body; security scanner flags `<iframe>` permitted on the public surface.

### Pitfall 11: FullCalendar timezone mismatch — events show on wrong day

**What goes wrong:** Server stores `events.starts_at` as `timestamptz` (UTC), but FullCalendar's Vue 3 wrapper interprets the ISO string as local time by default; events that start at `00:30 UTC` show on the previous day for Central European viewers.
**Why it happens:** ISO-8601 strings with `Z` (UTC) are correctly parsed by FC, but without explicit `timeZone: 'local'` or `timeZone: 'UTC'` config, FC uses ambient browser timezone — which is fine in most cases but trips testers in CI environments.
**How to avoid:** Pass ISO-8601 strings WITH `Z` suffix (Carbon's `->toIso8601String()` does this); set `timeZone: 'local'` explicitly in FC options so the intent is documented.
**Warning signs:** Operator manual smoke catches a match scheduled at midnight UTC displaying on the wrong calendar cell.

### Pitfall 12: Scheduler `everyMinute` + horizontal scaling = duplicate publishes

**What goes wrong:** Railway scales the `worker` service to 2 replicas; both run `php artisan schedule:run`; both find the same `articles.status=scheduled` row at `T+0`; both promote it to `published`; ArticleObserver fires twice; two `article_announce` rows land in `discord_outbound_messages`; Discord channel sees the embed twice.
**Why it happens:** Default `Schedule::command()` has no concurrency guard.
**How to avoid:** Chain `->withoutOverlapping()` (single-host single-execution) AND `->onOneServer()` (multi-host single-execution via cache-based lock). Both required for Railway multi-replica.
**Warning signs:** Production logs show duplicate `article_announce` `DiscordOutboundMessage` rows with `created_at` timestamps within 1 second of each other.

## Code Examples

### Tiptap JSON → HTML in a Controller

```php
// Source: filament-tiptap-editor 3.x README "Rendering Content"
// app/Http/Controllers/ArticleShowController.php

use App\Data\PublicArticleData;
use App\Models\Article;
use Illuminate\Http\Request;
use Inertia\Inertia;

public function __invoke(Article $article)
{
    abort_unless($article->status === 'published', 404);

    $bodyTranslations = $article->getTranslations('body');
    $locale = app()->getLocale();
    $bodyJson = $bodyTranslations[$locale] ?? $bodyTranslations['en'] ?? [];

    // tiptap_converter helper exposed by awcodes/filament-tiptap-editor;
    // backed by ueberdosis/tiptap-php
    $bodyHtml = tiptap_converter()->asHTML(
        is_string($bodyJson) ? json_decode($bodyJson, true) : $bodyJson
    );

    return Inertia::render('Articles/Show', [
        'article' => PublicArticleData::fromModel($article, $bodyHtml),
    ]);
}
```

### Postgres FTS migration (verified pattern)

```php
// Source: postgresql.org/docs/current/textsearch-tables.html
// database/migrations/2026_05_..._add_fts_to_articles.php

public function up(): void
{
    DB::statement('ALTER TABLE articles ADD COLUMN search_vector tsvector');
    DB::statement("
        CREATE OR REPLACE FUNCTION articles_fts_trigger() RETURNS trigger AS $$
        BEGIN
            NEW.search_vector :=
                to_tsvector('simple',
                    coalesce(NEW.title->>'en', '') || ' ' ||
                    coalesce(NEW.excerpt->>'en', '') || ' ' ||
                    coalesce(NEW.slug, ''));
            RETURN NEW;
        END
        $$ LANGUAGE plpgsql
    ");
    DB::statement('
        CREATE TRIGGER articles_fts_update
        BEFORE INSERT OR UPDATE ON articles
        FOR EACH ROW EXECUTE FUNCTION articles_fts_trigger()
    ');
    DB::statement('CREATE INDEX articles_search_vector_idx ON articles USING GIN (search_vector)');
    // Backfill existing rows (none in fresh install; matters for subsequent envs)
    DB::statement("
        UPDATE articles SET search_vector = to_tsvector('simple',
            coalesce(title->>'en', '') || ' ' ||
            coalesce(excerpt->>'en', '') || ' ' ||
            coalesce(slug, ''))
    ");
}

public function down(): void
{
    DB::statement('DROP TRIGGER IF EXISTS articles_fts_update ON articles');
    DB::statement('DROP FUNCTION IF EXISTS articles_fts_trigger()');
    DB::statement('DROP INDEX IF EXISTS articles_search_vector_idx');
    DB::statement('ALTER TABLE articles DROP COLUMN IF EXISTS search_vector');
}
```

### Filament ArticleResource form schema (compose-ready snippet)

```php
// Source: filament-tiptap-editor + Filament v3 SpatieMediaLibraryFileUpload docs
// app/Filament/Resources/ArticleResource.php

use Filament\Forms\Components\{Section, Select, TextInput, Toggle, DateTimePicker, SpatieMediaLibraryFileUpload};
use FilamentTiptapEditor\TiptapEditor;
use FilamentTiptapEditor\Enums\TiptapOutput;

public static function form(Form $form): Form
{
    return $form->schema([
        Section::make()->schema([
            TextInput::make('title.en')->label(__('admin.article.fields.title'))->required()->maxLength(200),
            TextInput::make('slug')->required()->maxLength(200)->unique(ignoreRecord: true)->disabledOn('edit'),
            Select::make('category_id')->relationship('category', 'name->en')->required(),
            TextInput::make('excerpt.en')->label(__('admin.article.fields.excerpt'))->maxLength(500),
            SpatieMediaLibraryFileUpload::make('hero')->collection('hero')->image()->imageEditor()
                ->responsiveImages()->maxSize(5 * 1024)->columnSpanFull(),
            TiptapEditor::make('body.en')->label(__('admin.article.fields.body'))
                ->profile('default')->output(TiptapOutput::Json)
                ->disk('public')->directory('article-media')
                ->maxContentWidth('5xl')->required()->columnSpanFull(),
        ]),
        Section::make(__('admin.article.publication'))->schema([
            Select::make('status')->options([
                'draft' => __('admin.article.status.draft'),
                'scheduled' => __('admin.article.status.scheduled'),
                'published' => __('admin.article.status.published'),
            ])->required()->reactive(),
            DateTimePicker::make('scheduled_at')->visible(fn (callable $get) => $get('status') === 'scheduled'),
            Toggle::make('allow_discord_announce')->default(true),
        ])->columns(2),
    ]);
}
```

### Sitemap artisan command + scheduler hook

```php
// Source: Context7 spatie/laravel-sitemap "Create and Add URLs to Sitemap"
// app/Console/Commands/SitemapGenerateCommand.php

use Spatie\Sitemap\Sitemap;
use App\Models\{Article, Clan, Tournament};

class SitemapGenerateCommand extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'Regenerate public/sitemap.xml';

    public function handle(): int
    {
        Sitemap::create()
            ->add('/')
            ->add('/clans')
            ->add('/matches')
            ->add('/tournaments')
            ->add('/blog')
            ->add('/events')
            ->add(Article::where('status', 'published')->get())
            ->add(Clan::all())
            ->add(Tournament::where('is_public', true)->get())
            ->writeToFile(public_path('sitemap.xml'));

        $this->info('sitemap.xml written.');
        return self::SUCCESS;
    }
}

// routes/console.php
Schedule::command('sitemap:generate')->dailyAt('03:00')->onOneServer();
Schedule::command('articles:publish-scheduled')->everyMinute()->withoutOverlapping()->onOneServer();
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Storing rich text as HTML | TipTap JSON (ProseMirror) | TipTap 1.0 (2019) — mainstream 2022 | Custom blocks, merge tags, AI-driven content all become viable |
| Filament v2 TinyMCE plugin | Filament v3 + `awcodes/filament-tiptap-editor` | Filament v3 release (2023) | ProseMirror is the modern WYSIWYG substrate; TinyMCE is legacy |
| Frontend-only `<head>` meta tags | SSR-rendered Inertia `<Head>` | Inertia v1.2 (2022) head component; v2.0 (2024) refined | Social crawlers see correct OG tags; without SSR, OG previews are universally broken |
| Meilisearch / Algolia first | Postgres FTS first, escalate if needed | Postgres 12+ FTS performance + GIN improvements | Saves a service deploy + indexing pipeline for sub-100K record corpuses |
| Hand-rolled image conversion via CLI | spatie/laravel-medialibrary with `spatie/image` | medialibrary v10 (2023) | Polymorphic, queued, responsive images, Filament integration |

**Deprecated/outdated:**
- TinyMCE-based Filament plugins (e.g., `mohamedsabil83/filament-forms-tinyeditor`) — still maintained but lose to Tiptap on extensibility.
- `pmatseykanets/laravel-scout-postgres` — Laravel Scout PG driver; useful when you want Scout's drop-in `searchable()` API, but adds Scout indirection on top of FTS for no Phase 7 benefit. Direct `tsvector + GIN + DB::raw` is simpler.
- `cviebrock/eloquent-sluggable` — handy, but `Str::slug()` + a `disabledOn('edit')` Filament input is fine for a single-developer round-1.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Filament v3.3's built-in `RichEditor` is Trix-based (not ProseMirror) | Standard Stack / Alternatives | Low. If RichEditor has improved to ProseMirror, the only impact is awcodes/filament-tiptap-editor is "nice to have" not "required" — but the latter still wins on custom-block extensibility, so the recommendation stands. |
| A2 | English-only `'simple'` text-search config is acceptable for v1 search relevance (no stemming) | Architecture Patterns Pattern 3 | Medium. If author writes "fighting" and searcher types "fight", v1 won't match. Mitigation: swap to `'pg_catalog.english'` config — one-line migration. Defer to operator feedback. |
| A3 | medialibrary's queued conversions are acceptable for hero images (small visible lag is fine) | Architecture Patterns Pattern 2 | Low. og-image conversion is `->nonQueued()` for SEO immediacy; hero + thumb conversions can lag a few seconds without UX impact. |
| A4 | Markdown-it is NOT needed in v1 — TipTap JSON via `tiptap_converter()->asHTML()` is the sole render path | Standard Stack / Supporting | Low. If owner asks for "let me paste raw markdown into a textarea and store it as-is", add `league/commonmark` ^2.8 — one composer require. |
| A5 | Phase 5 worker service can host the SSR Node process OR a 6th `ssr` service is added — both viable | Pattern 5 | Medium. Operator preference. Reseachers recommend split service for prod (cleaner failure isolation) and worker-co-host for dev (less RAM). Both supported by the same `php artisan inertia:start-ssr` invocation. |
| A6 | FullCalendar's MIT license + recurring events covers Trenchwars needs (no FullCalendar Premium needed) | Standard Stack / Supporting | Low. Round-1 doesn't need timeline, resource view, or recurring events with exceptions (matches/tournaments aren't recurring). |
| A7 | `cms-editor` Spatie role placeholder shipped in plan 01-11 (`PermissionSeeder.php`) needs Filament-panel-access permissions added in Phase 7, not a new role | Code Context | Low. Verified via `grep cms-editor`. Phase 7 task is to grant the role `admin-access` permission and an explicit `articles.*` permission set. |
| A8 | Sitemap can stay as a single `sitemap.xml` for v1 (< 1000 URLs) — no need for `SitemapIndex` | Pitfall 7 | Low. League has under 100 clans, under 1000 players, under 100 articles in year 1. |
| A9 | The Postgres trigger approach for tsvector is preferred over expression GIN indexes for our query mix | Pattern 3 | Low. Both work; triggers survive raw SQL writes (seeders) — expression indexes survive but cost more on every UPDATE. Trigger is the canonical recommendation per multiple sources. |
| A10 | Discord article-publish announce uses the host clan's channel — but Article has no host clan in the schema. v1 routes to a global "league announcements" channel | Pattern 8 | Medium. Discuss with owner before plan: schema needs `articles.discord_announce_channel_id` nullable text OR a config-driven global channel. Recommendation: global channel from config (`config/discord.php` `league_announce_channel_id`). |

**These 10 assumptions need user confirmation in `/gsd-plan-phase` discuss step before execution.**

## Open Questions

1. **Discord article-announce channel routing — global league channel vs per-article override?**
   - What we know: Phase 5 routes match announces to `clan.discord_announce_channel_id`; articles have no clan.
   - What's unclear: Is there a league-wide announcements channel ID configured somewhere, or do we add a per-article `announce_channel_id` text column?
   - Recommendation: Add a global `discord.league_announce_channel_id` config key; default articles to that channel; allow per-article override via Filament textInput in v1. Surface in discuss-phase.

2. **Editorial team at launch — which Discord users get the `cms-editor` role?**
   - What we know: spatie/laravel-permission `cms-editor` role placeholder seeded in Phase 1; PROJECT.md Open Questions explicitly flags this.
   - What's unclear: Initial roster.
   - Recommendation: Reuse `trenchwars:make-admin` artisan command pattern (Phase 1) to create a `trenchwars:make-cms-editor <discord_id>` command in Phase 7.

3. **Initial blog categories — what are the seed values?**
   - What we know: PROJECT.md Open Questions suggests "News, Match Reports, Tournament Updates, Community."
   - What's unclear: Operator confirmation; translatable JSONB needs at least `en` values.
   - Recommendation: Ship a `CategorySeeder` with those 4 categories; flag to operator in phase verification as "first manual smoke: confirm or edit category names."

4. **Slug collision policy — random suffix vs error?**
   - What we know: Phase 1 plan 01-09 chose random-4-char suffix for player slugs; clans use admin-set slugs.
   - What's unclear: For articles, do we accept author-provided slugs and error on collision, or auto-suffix?
   - Recommendation: Accept author-provided slug + unique-rule validation in Filament; surface a clear validation error on collision. The Article slug is a permalink — auto-suffixing breaks shared links.

5. **Per-locale slugs vs single slug?**
   - What we know: D-013 says translatable user content uses JSONB; slug is technically content.
   - What's unclear: `/blog/cs-uvodni-vyssi-clanek` vs `/blog/intro-higher-article` for the Czech version of the same article.
   - Recommendation: Single non-translatable slug in v1 (column `articles.slug` text UNIQUE); translatable slugs are CMS-V2.

6. **Calendar event color scheme — categorical or per-clan?**
   - What we know: `events.eventable_type` distinguishes match/tournament/article.
   - What's unclear: Whether matches color by host clan or event type.
   - Recommendation: Color by event type in v1 (match=blue, tournament=purple, article=green). Per-clan colors are CMS-V2.

7. **SSR — does the operator want it on for /admin too?**
   - What we know: Performance + SEO argue for SSR on public pages; admin pages are behind auth and don't need SSR.
   - What's unclear: Default. `INERTIA_SSR_ENABLED=true` flips it on globally; route-level toggle is possible but adds complexity.
   - Recommendation: Global toggle on in production. Filament panel is already SSR-friendly (Livewire SSR by design). Phase 1 plan 01-06 left SSR disabled in dev (Pitfall 8 mitigation); production stays enabled.

8. **Markdown body as v2 input mode?**
   - What we know: TipTap JSON is the v1 default; raw markdown was floated in the CONTEXT.md.
   - What's unclear: Is markdown input a deferred secondary path, or out of scope?
   - Recommendation: Out of scope for v1. Defer to v2 along with Newsletter / threaded comments (REQUIREMENTS line 75-77).

## Environment Availability

> Skip check (`spatie/laravel-medialibrary` needs ImageMagick or GD — both are present in the existing web container per Phase 1 plan 01-04 entrypoint. Verify before planning.)

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP 8.4 | All composer deps | ✓ (container, D-021) | 8.4.x | — |
| PHP `intl` extension | spatie/laravel-translatable JSONB | ✓ (container) | bundled with PHP 8.4 | — |
| PHP `gd` or `imagick` extension | spatie/image (used by medialibrary) | NEEDS VERIFY | — | Use whichever is present; spatie/image abstracts both |
| Postgres 16 with FTS | tsvector + GIN | ✓ (container, D-016) | 16.x | — |
| Postgres `plpgsql` language | trigger functions | ✓ (default-installed; comes with Postgres) | — | — |
| Node 22 | SSR runtime | ✓ (container `worker`, can extend or split `ssr` service) | 22.x | — |
| Laravel Scheduler cron (1-min-tick) | Auto-publish + sitemap regen | ✓ (Railway scheduler / docker-compose has it via Horizon worker entry) | — | — |
| Redis 7 | Horizon (queued media conversions, queued schedule locks) | ✓ (container, D-021) | 7.x | — |
| `storage:link` from `apps/web/storage/app/public` to `apps/web/public/storage` | Medialibrary public-disk URLs | NEEDS VERIFY | — | Add to Dockerfile entrypoint or deploy step |

**Missing dependencies with no fallback:** None confirmed yet — `gd`/`imagick` presence + `storage:link` validity should be verified in Phase 7 wave 0.
**Missing dependencies with fallback:** None.

**Action for planner:** Phase 7 wave 0 should include a smoke test that runs `php -m | grep -iE "gd|imagick"` inside the web container and that `apps/web/public/storage` is a symlink — flag before any media library work.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest 4.7 (PHPUnit 12.5 underneath); + Vitest 1.x for bot tests (carry-forward) |
| Config file | `apps/web/phpunit.xml` + `apps/web/tests/Pest.php` |
| Quick run command | `docker compose exec web ./vendor/bin/pest --filter=<Class> --no-coverage` |
| Full suite command | `docker compose exec web ./vendor/bin/pest --no-coverage` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REQ-goal-cms SC-1 | cms-editor creates / schedules / publishes Article via Filament; status flips Draft→Scheduled→Published via scheduler | Feature (Filament + scheduler) | `pest --filter='ArticleResource\|ArticlesPublishScheduledCommand'` | ❌ Wave 0 |
| REQ-goal-cms SC-2 | Public visitor reads `/blog`, `/blog/{slug}` (server-rendered HTML body), `/events` calendar | Feature (Inertia render + DTO) | `pest --filter='ArticleIndex\|ArticleShow\|EventsCalendar'` | ❌ Wave 0 |
| REQ-success-public-browse SC-3 | All public surfaces reachable without auth; SSR enabled in production | Smoke (HTTP 200 + Inertia component) | `pest --filter='PublicSurfaceReachable\|SsrEnabled'` | ❌ Wave 0 |
| REQ-goal-cms SC-4 | Postgres FTS across articles/clans/players via `/search?q=` | Feature (DB + DTO + privacy gate) | `pest --filter='SearchService\|SearchController\|FtsBackfill'` | ❌ Wave 0 |
| REQ-goal-cms SC-5 | Sitemap + meta tags emitted; `<html lang>` reflects active locale; Discord announce on publish (per-article configurable) | Feature (artisan + Inertia Head + Observer + outbox) | `pest --filter='SitemapGenerate\|ArticleHeadMeta\|ArticleObserver\|ArticleAnnounceOutbound'` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `docker compose exec web ./vendor/bin/pest --filter=<thing-just-changed> --no-coverage`
- **Per wave merge:** `docker compose exec web ./vendor/bin/pest --no-coverage`
- **Phase gate:** Full Pest + Pint + PHPStan L8 + vue-tsc + bot tsc + bot Vitest + shared-types tsc — all 7 gates GREEN before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Articles/ArticleResourcePresentTest.php` — covers REQ-goal-cms SC-1 Filament resource exists + form schema reachable
- [ ] `tests/Feature/Articles/ArticlePublishWorkflowTest.php` — covers Draft→Scheduled→Published flip via artisan + observer
- [ ] `tests/Feature/Articles/ArticleIndexPageTest.php` + `tests/Feature/Articles/ArticleShowPageTest.php` — public surface
- [ ] `tests/Feature/Articles/ArticleHeadMetaTest.php` — Inertia Head meta-tag presence (og:image, twitter:card, description, head-key uniqueness)
- [ ] `tests/Feature/Events/EventsCalendarPageTest.php` + `tests/Feature/Events/EventsFeedJsonControllerTest.php` — calendar + JSON feed
- [ ] `tests/Feature/Search/SearchServiceTest.php` — FTS hit + ts_rank ordering + PlayerPrivacyGate filtering
- [ ] `tests/Feature/Search/SearchControllerTest.php` — endpoint smoke + sanitised query
- [ ] `tests/Feature/Sitemap/SitemapGenerateCommandTest.php` — artisan produces valid XML containing expected URLs
- [ ] `tests/Feature/I18n/CmsI18nKeyCoverageTest.php` — D-013 enforcement on all new `admin.article.*`, `admin.category.*`, `cms.*`, `events.*`, `search.*` keys (mirror Phase 6 `TournamentI18nKeyCoverageTest`)
- [ ] `tests/Feature/Observers/ArticleObserverTest.php` — status flip → Event morphOne; outbound row; idempotency
- [ ] `tests/Feature/Outbound/ArticleAnnounceOutboundTest.php` — `article_announce` row lands in `discord_outbound_messages` with correct payload shape
- [ ] `tests/Unit/Data/PublicArticleDataTest.php` — DTO shape; tiptap_converter integration smoke
- [ ] `tests/Feature/Articles/FtsBackfillTest.php` — `search_vector` column populated on insert + update (trigger smoke)
- [ ] `tests/Feature/Ssr/SsrBundleExistsTest.php` — `bootstrap/ssr/ssr.mjs` exists; `INERTIA_SSR_ENABLED=true` honoured by config
- [ ] `apps/web/lang/en/cms.php`, `apps/web/lang/en/events.php`, `apps/web/lang/en/search.php` — pre-shipped i18n namespaces (Phase 6 D-06-01-C precedent to prevent NoHardcodedStringsTest + MissingTranslationException mid-execution)
- [ ] Wave-0 stub idiom: bare functional Pest (Phase 5 D-05-01-C, Phase 6 D-06-01-B) — no namespace, no per-file `uses()` call

## Project Constraints (from CLAUDE.md)

> Directives extracted from `./CLAUDE.md` and `.planning/PROJECT.md` that must constrain Phase 7 plans:

| Constraint | Source | Phase 7 impact |
|-----------|--------|----------------|
| **Container-only commands** (D-021 LOCKED) | CLAUDE.md §1 | Every composer/npm/php/pest/pint/phpstan invocation prefixed `docker compose exec web` / `docker compose run --rm bot`. Plans must NEVER reference host PHP. |
| **Pinned stack** (D-001) | CLAUDE.md §2 | Filament `^3.3`, Inertia `^2.0`, Vue 3 `^3.5`, Tailwind v4 — all libraries Phase 7 adds must be compatible. Verified: filament-tiptap-editor 3.5.x targets Filament v3; medialibrary v11 targets Laravel 11/12; spatie/laravel-sitemap v8 targets Laravel 11+. |
| **Pint + PHPStan L8 CI gates** | CLAUDE.md §3 | New code passes both. Avoid `phpstan-baseline.neon` edits (regen only with explicit user request). |
| **Pest convention (NOT PHPUnit syntax)** | CLAUDE.md §4 | All new tests in Pest `it(...)` / `expect(...)`. Wave 0 stubs per Phase 5 D-05-01-C / Phase 6 D-06-01-B bare functional convention. |
| **Path conventions** (D-015) | CLAUDE.md §5 | All PHP under `apps/web/`. No composer.json hoisting. Frontend Vue under `apps/web/resources/js/`. |
| **Never commit secrets** | CLAUDE.md §6 | `.env.example` documents `INERTIA_SSR_ENABLED=false` shape only. No real Discord channel IDs committed. |
| **i18n every UI string via `__()` / `t()`** (D-013) | CLAUDE.md §7 | All article/category/calendar/search/sitemap strings flow through translation namespaces. Hardcoded strings fail `NoHardcodedStringsTest`. |
| **Translatable user content via spatie/laravel-translatable JSONB** | CLAUDE.md §7 | Article.title / excerpt / body JSONB; Category.name JSONB. Slug is intentionally non-translatable in v1 (Open Question 5). |
| **Discord bot is thin display layer; no DB writes from bot; no business logic in bot** (D-004) | CLAUDE.md §8 | Article-publish announce flows web → `discord_outbound_messages` → worker → bot. Bot doesn't query articles directly. |
| **Filament covers every domain entity** (D-012) | CLAUDE.md §8 | ArticleResource + CategoryResource ship in Phase 7. Per-resource Audit tab. LogsActivity on both. |
| **`App\Models\GameMatch` direct import (D-04-03-A LOCKED across all prior phases)** | STATE.md Phase 4-6 close + Phase 6 verification | Every Phase 7 plan that references the match model uses `use App\Models\GameMatch;` directly. Zero `App\Models\Match as MatchModel` alias anywhere. |
| **NFR: SSR enabled in production** | CLAUDE.md §8 NFR | SC-3 explicit acceptance. Phase 7 flips `INERTIA_SSR_ENABLED=true` in production env. |
| **Activity log writes append-only via `LogsActivity` trait** | CLAUDE.md §6 | ArticleResource + CategoryResource admin pages — no edit/delete on `activity_log` rows. |
| **Postgres extensions enabled by first migration** | CLAUDE.md §6 | Phase 1 already enabled `uuid-ossp` + `citext`. Phase 7's tsvector uses Postgres built-in `pg_catalog` — no extension required. |

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes | Reuse Phase 1 Discord OAuth (D-002 LOCKED); `cms-editor` Spatie role gates admin panel access. No new auth surface in Phase 7. |
| V3 Session Management | yes | Reuse Laravel session driver; SSR doesn't change cookie shape; `SameSite=Lax` + `HttpOnly` + `Secure` (prod) — verified by CLAUDE.md §6 + Phase 1 RESEARCH Pitfall 3. |
| V4 Access Control | yes | `cms-editor` role + `articles.*` permissions on ArticleResource; Filament panel guard `web` (CLAUDE.md §6 — D-018 Pitfall 4 mitigation continued); public `/blog`, `/events`, `/search` open by design (SC-3). |
| V5 Input Validation | yes | Laravel FormRequest validation on search query (`q` alphanum + length max); slug regex; FullCalendar `start`/`end` date validation; tsquery via `plainto_tsquery` parameter-bound. Tiptap profile pinned to allow only safe nodes (Pitfall 10). |
| V6 Cryptography | no | Phase 7 introduces no new cryptographic surface; sitemap is unsigned (public); article body has no encryption needs. |
| V7 Error Handling | yes | DB CHECK constraint (`article_announce` enum) prevents bad outbound rows; `try/catch` around tiptap_converter (malformed JSON) returns empty string; sitemap.xml regen is non-fatal on individual model errors. |
| V8 Data Protection | yes | PlayerPrivacyGate (D-018) filters player search results (FTS must respect per-section + global tier flags). |
| V11 Business Logic | yes | Auto-publish state machine: only `draft|scheduled` → `published`, never backwards; `withoutOverlapping()` + `onOneServer()` prevent duplicate publishes (Pitfall 12); article slug uniqueness enforced at DB layer. |
| V12 Files and Resources | yes | Medialibrary `acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])` + `maxSize(5 * 1024)`; medialibrary's `addMedia()` strips EXIF by default (configurable per disk). |
| V13 API and Web Service | yes | `/events/feed.json` validates `start` / `end` date params; `/search?q=` validates `q` length + char class; both rate-limited (recommendation: `throttle:60,1` matching Phase 6 tournament JSON endpoint precedent). |
| V14 Configuration | yes | `INERTIA_SSR_ENABLED` defaults `false` (safe fallback); medialibrary config published with sensible defaults; Tiptap profile pinned in `config/filament-tiptap-editor.php`. |

### Known Threat Patterns for Laravel + Filament + Inertia + Postgres + Discord stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Stored XSS via Tiptap-permitted iframe/script | Tampering, Info Disclosure | Pin TiptapEditor profile to safe-node list (Pitfall 10); Pest assertion `expect($html)->not->toContain('<iframe');` |
| SQL injection via search query | Tampering | `plainto_tsquery(?)` parameter-bound; never concat user input into `to_tsquery` |
| Reflected XSS via search query echo | Tampering | Inertia auto-escapes Vue templates (`{{ q }}` not `v-html`); use `v-text` for user echo |
| CSRF on Filament admin actions | Tampering | Laravel session middleware + Filament's built-in CSRF token (verified by Phase 1 Pitfall 3 — Inertia handles XSRF via cookie) |
| Path traversal via medialibrary upload | Tampering | Medialibrary normalises filenames via `Spatie\MediaLibrary\Support\PathGenerator`; original filename sanitised |
| Image bomb (decompression DOS) | DoS | Medialibrary uses `spatie/image` which sets memory_limit guards; cap `maxSize(5 * 1024)` KB at form layer |
| OG image hot-linking abuse | DoS | Cloudflare / Railway-edge CDN caches OG image URLs; no inline mitigation needed in app code |
| Discord webhook flooding via repeated publish | Info Disclosure (channel spam) | Observer-level guard: only first `draft|scheduled → published` transition enqueues outbound (Pitfall 10 in observer code shown above checks `getOriginal('status') === 'published'`) |
| Race condition on Article.slug uniqueness | Tampering | DB UNIQUE constraint on `articles.slug` is the defence-in-depth; Filament FormRequest `unique:articles,slug` rule is the UX layer |
| Inertia SSR remote code execution via unsafe `eval` in component | Code Injection | Inertia SSR runs trusted bundle in trusted Node sandbox; never evaluate user input as code. Verify build pipeline doesn't compile user-supplied templates. |
| Sitemap leaking private resources | Info Disclosure | Sitemap generator queries with explicit visibility filters (`->where('is_public', true)`, `->where('status', 'published')`) — never `Article::all()` |
| FTS leaking private player data | Info Disclosure | SearchService runs PlayerPrivacyGate (D-018) over player hits before merging into results |

### Phase 7 Security-First Tasks

These belong in the planner's task list, not deferred:

1. **Tiptap profile lockdown** — explicit `->tools([...])` list pinning safe nodes; Pest assertion that rendered article body never contains `<script` or `<iframe`.
2. **PlayerPrivacyGate integration in SearchService** — D-018 was the Phase 2 contract; Phase 7 FTS must invoke `PlayerPrivacyGate::canShowInSearch($player, $viewer)` before returning the player hit.
3. **Rate limit `/search?q=` and `/events/feed.json`** — `throttle:60,1` precedent from Phase 6 `TournamentPublicJsonController`.
4. **Medialibrary file-type allowlist + size cap** at the form layer AND `config/media-library.php` `image_optimizers` enabled.
5. **`article_announce` outbound channel — global league channel via config, not authoring surface** — prevents authors from spamming arbitrary Discord channels.

## Sources

### Primary (HIGH confidence)
- Context7 `/spatie/laravel-medialibrary` — Defining Media Conversions, Filament integration, Image Manipulations
- Context7 `/awcodes/filament-tiptap-editor` — Basic Field Usage, Output Format, tiptap_converter helper, Custom Blocks
- Context7 `/spatie/laravel-sitemap` — Sitemapable interface, manual Sitemap::create, Eloquent collections
- Context7 `/fullcalendar/fullcalendar-docs` — Vue 3 Integration, JSON Event Feed
- Context7 `/markdown-it/markdown-it` — basic configure (verified but markdown-it deferred to v2)
- Packagist `/p2/{spatie/laravel-medialibrary,spatie/laravel-sitemap,awcodes/filament-tiptap-editor,ueberdosis/tiptap-php}.json` — version + publish date verification (2026-05-14)
- npm registry — `@fullcalendar/{vue3,daygrid,timegrid,interaction,core}@6.1.20`, `markdown-it@14.1.1`, `dompurify@3.4.3`, `jsdom@29.1.1`
- `apps/web/composer.json` + `apps/web/package.json` — existing pinned versions inspected
- `apps/web/database/migrations/2026_05_14_100500_create_events_table.php` — Phase 4 polymorphic events table schema
- `apps/web/config/inertia.php` — Inertia SSR config already scaffolded in Phase 1 plan 01-06
- `apps/web/resources/js/ssr.ts` — SSR entry already exists with @inertiajs/vue3/server + @vue/server-renderer + ZiggyVue
- `apps/web/vite.config.ts` — `ssr: 'resources/js/ssr.ts'` input already configured
- `apps/web/database/migrations/2026_05_15_100600_extend_discord_outbound_message_types_for_tournament_announce.php` — canonical DROP+ADD CHECK migration idiom (Phase 6 plan 06-10)
- `apps/web/app/Observers/TournamentObserver.php` — observer→outbox pattern reference for ArticleObserver
- `apps/web/database/seeders/PermissionSeeder.php` — `Role::findOrCreate('cms-editor', 'web')` placeholder confirmed at line 43
- `apps/web/app/Models/Event.php` — polymorphic morphTo precedent

### Secondary (MEDIUM confidence)
- [Inertia.js docs v2 — SSR](https://inertiajs.com/docs/v2/advanced/server-side-rendering) (cross-referenced web search)
- [Inertia.js docs — Title and Meta](https://inertiajs.com/title-and-meta) (cross-referenced web search; `head-key` deduplication)
- [PostgreSQL 18 docs — Full Text Search Tables and Indexes](https://www.postgresql.org/docs/current/textsearch-tables.html)
- [Laravel docs 12.x — Task Scheduling](https://laravel.com/docs/12.x/scheduling)
- [thoughtbot — Optimizing FTS with tsvector columns and triggers](https://thoughtbot.com/blog/optimizing-full-text-search-with-postgres-tsvector-columns-and-triggers)
- [pganalyze — Understanding Postgres GIN Indexes](https://pganalyze.com/blog/gin-index)
- [Filament v3 docs — Spatie Media Library plugin](https://filamentphp.com/plugins/filament-spatie-media-library)
- [awcodes/filament-tiptap-editor 3.x README](https://github.com/awcodes/filament-tiptap-editor/blob/3.x/README.md)
- [Fly.io Laravel — Inertia SSR deployment](https://fly.io/docs/laravel/advanced-guides/using-inertia-ssr/)
- [Inertia GitHub Discussion #1930 — SSR with Docker](https://github.com/inertiajs/inertia/discussions/1930)

### Tertiary (LOW confidence)
- [matthewdaly.co.uk — Full text search with Laravel and PostgreSQL](https://matthewdaly.co.uk/blog/2017/12/02/full-text-search-with-laravel-and-postgresql/) — older but the GIN/tsvector migration shape is unchanged
- [pmatseykanets/laravel-scout-postgres](https://github.com/pmatseykanets/laravel-scout-postgres) — alternative not chosen, included for completeness
- [danielabaron.me — Speeding up FTS with persistent tsvectors](https://danielabaron.me/blog/speed-up-pg-fts-with-persistent-ts-vectors/) — corroborates tsvector column + trigger over expression index

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — Context7 + packagist + npm registry triangulation for all 4 new PHP packages and 5 new npm packages
- Architecture patterns: HIGH — Context7 confirms Filament v3 integration shape for tiptap-editor + medialibrary; sitemap + scheduler patterns are first-party Laravel
- Pitfalls: HIGH — 12 pitfalls each backed by at least one Context7 / official doc citation or grep'd repo evidence
- FTS: HIGH — Postgres docs + thoughtbot + pganalyze cross-confirm trigger + GIN approach
- SSR: MEDIUM — Inertia v2 SSR docs are clear; SSR-in-docker is a "many recipes exist, none official" space — Pattern 5 recommendation includes both worker-host and split-service variants, operator picks at plan time
- Discord outbound: HIGH — Phase 5/6 codebase precedent grep'd and confirmed
- Security: MEDIUM — ASVS map drafted in pattern with stack; Phase 7 isn't introducing crypto so the surface is narrow

**Research date:** 2026-05-14
**Valid until:** 2026-06-13 (30 days — stack is stable; revisit if Filament v4 lands and forces a re-pin)
