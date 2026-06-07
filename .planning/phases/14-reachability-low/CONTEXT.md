# Phase 14 Context: Reachability — LOW gaps

**Milestone:** v1.2 Reachability completion
**Goal:** Close the LOW-severity reachability gaps from the 2026-06-06 feature audit.
**Spec source:** ROADMAP.md Phase 14 (skip_discuss=true — ROADMAP goal is the spec).

## Plans

### 14-01 — Ban enforcement at the auth layer (REACH-04)
`BanService::isCurrentlyBanned` + `User::activeBan` exist and are correct, but nothing calls them in
the request lifecycle — a banned user completes Discord OAuth and accesses authenticated routes
unimpeded. A dead docblock on `User::activeBan` references a never-built "ban-check middleware (plan
09-11)". Add a middleware that denies a currently-banned user authenticated access (log them out /
block the request with a clear message) and applies on the `auth` route group; remove/correct the dead
docblock. Feature test: a banned user is denied authenticated access; a non-banned user is unaffected.

### 14-02 — Filament form-publish sets published_at (REACH-05)
Publishing an article by flipping the Filament status Select to `published` on the edit form does NOT
set `published_at` (only the scheduler path via `ArticleStatusService` does), so it sorts
unpredictably under `orderByDesc('published_at')` on `/blog`. Route the Filament publish through
`ArticleStatusService` (e.g. EditArticle `mutateFormDataBeforeSave`/`afterSave` hook or observer) so a
form-published article gets `published_at` set. Test: publishing via the Filament form sets
`published_at`.

### 14-03 — MatchPlayerStat admin correction UI (REACH-06)
`MatchPlayerStat` docblock claims a "admin manually corrects a stat" flow (LogsActivity covers it) but
there is no Filament surface — `MatchResource` registers Slots/AccessRules/Result/Mvps/Bookings, not
stats. Add a relation manager on `MatchResource` exposing per-player stats with edit (the
`MatchPlayerStatObserver` audit then fires on update). Test: relation manager registered + an edit
persists + audits.

### 14-04 — MatchEvent read-only admin view (REACH-07)
`MatchEvent` (the append-only normalised CRCON event stream feeding stat aggregation + result
inference) is captured + audited but exposed nowhere in admin — an admin curating a low-confidence
result (`manual_entry_required`) can't inspect the underlying event timeline. Add a read-only relation
manager on `MatchResource` listing match events (immutable by design — no create/edit/delete). Needs a
`GameMatch::events()`/`matchEvents()` relation. Test: relation manager registered + lists events.

## Constraints
- Container-only commands (D-021). Tests via `make pest` / `docker compose exec web ./vendor/bin/pest`.
- All gates stay green: Pest, PHPStan L8, Pint, vue-tsc. i18n: every UI string via `t()`/`__()`.
- Atomic commit per plan with a SUMMARY.
- Audit log is append-only (CLAUDE.md §6) — MatchEvent view is read-only; MatchPlayerStat edit logs via the trait.
