---
phase: 7
slug: cms
status: approved
nyquist_compliant: true
wave_0_complete: false
created: 2026-05-14
approved: 2026-05-14
---

# Phase 7 — Validation Strategy

## Test Infrastructure
- Pest 4 (web) + Vitest (bot — minimal)
- `make pest ARGS="--filter=Cms or Article or Search or Sitemap"` quick
- Full suite (web + bot)

## Per-Plan Map

| Plan | Wave | Focus |
|------|------|-------|
| 07-01 | 0 | Wave 0 — composer require + factory stubs + RED Pest stubs + i18n |
| 07-02 | 1 | Migrations (articles, categories, FTS tsvector + triggers) |
| 07-03 | 2 | Models (Article, Category) + factories + MediaLibrary integration |
| 07-04 | 2 | spatie/permission cms-editor role + policies |
| 07-05 | 3 | Filament ArticleResource + CategoryResource + Tiptap editor |
| 07-06 | 3 | ArticleObserver + Event sync + Discord announce outbound + Article status state machine |
| 07-07 | 4 | Laravel Scheduler auto-publish + scheduled→published transition |
| 07-08 | 4 | Postgres FTS service (UNION articles+clans+players) + privacy gate |
| 07-09 | 5 | Public controllers (BlogIndex, BlogShow, EventsCalendar, Search) + routes + Inertia head meta |
| 07-10 | 6 | Public Vue pages (Blog/Index, Blog/Show with markdown-it/tiptap render, Events calendar with FullCalendar, Search results) |
| 07-11 | 6 | Inertia v2 SSR enable for public routes + Node sidecar in docker-compose |
| 07-12 | 7 | sitemap generator + meta tags + html lang + i18n + audit + presence tests |
| 07-13 | 8 | [BLOCKING] Phase verification |

## Wave 0 Requirements
- composer require spatie/laravel-medialibrary + awcodes/filament-tiptap-editor + ueberdosis/tiptap-php + spatie/laravel-sitemap
- pnpm add @fullcalendar/vue3 + dependencies + markdown-it
- 14 RED Pest stubs

## Manual Smokes
- Filament editor flow: write article, schedule, publish
- Calendar UX month/week/day toggles
- Search ranking matches expectations
- Sitemap.xml accessible and valid
- SSR first paint on public routes
- Discord announce on publish

**Approval:** 2026-05-14 autonomous workflow.
