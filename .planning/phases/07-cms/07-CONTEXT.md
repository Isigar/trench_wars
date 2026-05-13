---
phase: 7
phase_name: CMS
gathered: 2026-05-14
status: Ready for planning
mode: Auto-generated (discuss skipped via workflow.skip_discuss)
---

# Phase 7: CMS — Context

<domain>
## Phase Boundary

Editorial surface — articles, categories, public calendar — so announcements and tournament write-ups ship from Filament with translatable content and scheduled publishing.

**Success Criteria** (5):
1. cms-editor creates/schedules/publishes Article in Filament (translatable title/excerpt/body via Tiptap, hero image via medialibrary, category) Draft→Scheduled→Published via Laravel Scheduler.
2. Public visitor browses `/blog`, opens `/blog/{slug}` (server-rendered HTML via markdown-it), views calendar at `/events` (month/week/day) populated by auto match/tournament events + editorial events.
3. Full round-1 public surface (clans/players/calendar/brackets/articles) reachable without auth; SSR enabled in production for first paint.
4. Postgres FTS search across articles/clans/players via header search bar + `/search?q=` results.
5. Sitemap + meta tags emitted; `<html lang>` reflects active locale; Discord announce on publish (per-article configurable).

**Depends on**: Phase 6 (tournaments+matches both surface as Events on calendar)
**Requirements**: REQ-goal-cms, REQ-success-public-browse
</domain>

<decisions>
## Locked & Discretionary

- **D-013** Translatable JSONB (Article.title/excerpt/body).
- **D-012** Filament Resource (ArticleResource, CategoryResource).
- **D-018** PlayerPrivacyGate respected on FTS player results.
- **D-021** Container-only.
- **D-04-03-A** `App\Models\GameMatch`.
- spatie/laravel-medialibrary for hero images (new package this phase).
- TipTap or similar rich text editor inside Filament.
- markdown-it for SSR HTML rendering on the public side.
- Laravel Scheduler for Draft→Published auto-publish.
- Inertia v2 SSR enabled in production (was off in P1).
- Postgres FTS (tsvector + GIN indexes).
</decisions>

<code_context>
- Phase 4 events polymorphic table — calendar reuses it.
- Phase 6 Tournament + Phase 4 GameMatch already create Event rows.
- packages/shared-types already exports all prior DTOs.
- LogsActivity on Article/Category for D-012.
</code_context>

<specifics>
## Specifics

**Tables:**
- `articles` (id, slug, category_id, title JSONB, excerpt JSONB, body JSONB, hero_media_id, status enum [draft/scheduled/published], scheduled_at, published_at, author_user_id, allow_discord_announce bool, ...)
- `categories` (id, slug, name JSONB, ...)
- `article_search_index` materialised tsvector OR `tsvector` column inline on articles
- spatie media table from medialibrary

**Public Routes:**
- /blog index, /blog/{slug} detail, /events calendar (3 views), /search query.

**Roles:**
- cms-editor (new spatie role) — Filament panel access + ArticleResource CRUD.
- admin can do everything.

**SSR:**
- enable @inertiajs/vue3 SSR on public routes only.

**FTS:**
- tsvector columns on articles.title+body+excerpt, clans.name+description, players.username+...
- single search endpoint with UNION across the 3 sources.
</specifics>

<deferred>
- Multi-author article workflows (Phase 9+).
- Comment threads (out of scope).
- Newsletter subscriptions (out of scope).
- Article translations beyond initial JSONB columns (translation memory infra deferred).
</deferred>
