---
phase: 06-tournaments-brackets
plan: 12
subsystem: ui
tags: [laravel, inertia, vue, svg, polling, etag, throttle, sc1-capstone, sc3]

requires:
  - phase: 06-tournaments-brackets
    provides: PublicTournamentData DTO + ETag, BracketGeneratorService, BracketMatchMaterialiserService, MatchResultObserver → BracketAdvancementService chain, TournamentStatusService + TournamentSeedingService, TournamentResource + 8 HeaderActions
  - phase: 04-matches-manual
    provides: PublicLayout.vue idiom, Inertia/Vue page conventions, MatchSlotMaterialiserService, MatchResult model
  - phase: 03-games-match-types
    provides: Game/GameMatchType/GameRole/GameMatchTypeRoleLimit catalogue
  - phase: 02-clans-tags-roles
    provides: Clan model + factory
  - phase: 01-foundations
    provides: i18n plumbing (useT composable, shared_namespaces), CSS variable palette, Pest test infrastructure

provides:
  - public Tournaments directory (GET /tournaments) — Inertia/Vue Index.vue
  - public Tournaments detail (GET /tournaments/{slug}) — 5-tab Inertia/Vue Show.vue
  - JSON polling endpoint (GET /tournaments/{slug}.json) with ETag + 304 short-circuit + throttle:60,1 rate limit
  - 30s polling composable (useTournamentPolling) with onUnmounted cleanup
  - SVG bracket renderer (BracketCanvas.vue) with stage-group offset (double-elim Pitfall 9 mitigation)
  - SC-1 capstone test — 8-clan single-elim end-to-end through observer chain
  - tournaments.{nav,directory,show,standings,participants} i18n keys + 4 bracket CSS palette vars
affects: [06-13, 06-14]

tech-stack:
  added: []
  patterns:
    - "Vue SVG bracket renderer with O(n) position map computed from round_number × position × 2^(round-1) verticalSpacing (RESEARCH Pattern 8)"
    - "Group-by-stage_type SVG y-offset for double-elim (Pitfall 9)"
    - "Inertia first-paint + JSON polling reuses same DTO (PublicTournamentData) — no shape drift between SSR and refresh"
    - "ETag deterministic over tournament.updated_at + sorted bracket(id:updated_at) — same input → identical etag → cheap 304 short-circuit"
    - "Laravel throttle:60,1 middleware on hot polling endpoint (T-06-12-01 DoS mitigation)"
    - "Routes ordered .json BEFORE {slug} so first-match-wins dispatcher captures .json suffix correctly; {slug} constrained to [A-Za-z0-9_-]+"
    - "Vue ambient typing via App.Data.* namespace instead of @trenchwars/shared-types (matches Phase 2/4 idiom for in-app Vue files)"

key-files:
  created:
    - apps/web/app/Http/Controllers/TournamentIndexController.php
    - apps/web/app/Http/Controllers/TournamentShowController.php
    - apps/web/app/Http/Controllers/TournamentPublicJsonController.php
    - apps/web/resources/js/pages/Tournaments/Index.vue
    - apps/web/resources/js/pages/Tournaments/Show.vue
    - apps/web/resources/js/components/tournaments/BracketCanvas.vue
    - apps/web/resources/js/components/tournaments/BracketNode.vue
    - apps/web/resources/js/components/tournaments/StandingsTable.vue
    - apps/web/resources/js/components/tournaments/ParticipantsList.vue
    - apps/web/resources/js/components/tournaments/TournamentScheduleList.vue
    - apps/web/resources/js/composables/useTournamentPolling.ts
  modified:
    - apps/web/routes/web.php
    - apps/web/resources/js/layouts/PublicLayout.vue
    - apps/web/lang/en/tournaments.php
    - apps/web/lang/en/common.php
    - apps/web/config/i18n.php
    - apps/web/resources/css/app.css
    - apps/web/tests/Feature/Tournaments/TournamentIndexPageTest.php
    - apps/web/tests/Feature/Tournaments/TournamentShowPageTest.php
    - apps/web/tests/Feature/Tournaments/TournamentPublicJsonControllerTest.php
    - apps/web/tests/Feature/Tournaments/TournamentEndToEndTest.php

key-decisions:
  - "Vue file imports use App.Data.* ambient namespace (not @trenchwars/shared-types) — matches Phase 2/4 in-app idiom; shared-types package consumption stays a bot/worker concern."
  - "Routes ordered .json BEFORE {slug} so Laravel's first-match-wins dispatcher captures the JSON suffix correctly; the {slug} route additionally constrains the slug via where('tournament', '[A-Za-z0-9_-]+') so a tournament with a dotted slug cannot shadow the .json endpoint."
  - "Added 'matches' and 'tournaments' to config/i18n.php shared_namespaces so the Vue t() helper can resolve their namespaced keys via Inertia.props.translations. Matches was missing — pre-existing gap surfaced as Rule 3 blocking issue while wiring tournament tabs."
  - "Bracket SVG palette added 4 CSS variables (winner/loser line + completed/pending node) per theme (dark + light). Phase 1 palette extension only — no new design tokens."
  - "Capstone test walks the bracket tree iteratively: pick next undecided+materialised bracket, create MatchResult, let the observer drive advancement; when no undecided+materialised bracket exists but a fully-populated unmaterialised bracket does, materialise it via materialiseFor() and continue. 32-iteration safety brake (worst case for 8-clan single-elim is 7)."

patterns-established:
  - "Pattern 1: 30s polling via useTournamentPolling composable with If-None-Match header; 304 short-circuit; onUnmounted clears interval. Reusable for any DTO with deterministic etag."
  - "Pattern 2: BracketCanvas SVG layout — x = (round-1) * COLUMN_WIDTH + 20; y = stageYOffset + (position-1) * verticalSpacing + verticalSpacing/2 with verticalSpacing = ROW_HEIGHT * 2^(round-1). Stage-grouped y-offset handles multi-stage formats (double-elim winners/losers/grand-final)."
  - "Pattern 3: Route declaration order matters for .json suffixes — declare specific routes BEFORE catch-all {slug} bindings."
  - "Pattern 4: Inertia + JSON polling endpoints reuse the SAME DTO (PublicTournamentData::fromModel) so first-paint and refresh shapes never drift."

requirements-completed: [REQ-success-tournament-end-to-end]

duration: ~40min
completed: 2026-05-14
---

# Phase 6 Plan 12: Public Tournament Surface + SC-1 Capstone Summary

**3 public controllers (Index / Show / JSON) + 7 Vue files (2 pages + 5 components) + 30s polling composable + SC-1 capstone end-to-end test for 8-clan single-elim happy path.**

## Performance

- **Duration:** ~40 min
- **Started:** 2026-05-14
- **Completed:** 2026-05-14
- **Tasks:** 2
- **Files created:** 12
- **Files modified:** 8
- **Commits:** 2 (per task) + this metadata commit

## Accomplishments

- Public 5-tab Vue page (`Tournaments/Show.vue`) bound to `PublicTournamentData` with reactive polling.
- `BracketCanvas.vue` SVG renderer implementing RESEARCH Pattern 8 (computed x/y position map) + Pitfall 9 stage-group y-offset for double-elim.
- `useTournamentPolling.ts` composable: setInterval(30_000) + If-None-Match header + 304 short-circuit + onUnmounted cleanup.
- `TournamentPublicJsonController` with ETag header, 304 short-circuit, and Laravel `throttle:60,1` rate limit (T-06-12-01).
- SC-1 capstone test (`TournamentEndToEndTest`) walks an 8-clan single-elim through the full service stack: status transitions → seeding → bracket generation → round-1 materialisation → 7-result observer chain → auto-completion → public page assertions.
- `i18n` shared namespaces now include `matches` + `tournaments` (Rule 3 fix — `matches` was missing).
- 19 GREEN tests (5 Index + 4 Show + 7 JSON + 2 Capstone + 1 i18n).
- vue-tsc, Pint, PHPStan level 8, NoHardcodedStringsTest all green.

## Task Commits

1. **Task 1: 3 public controllers + routes + GREEN tests** — `4e8d15b` (feat)
2. **Task 2: 7 Vue files + composable + PublicLayout amendment + SC-1 capstone** — `4b44ec4` (feat)

## Files Created/Modified

### Controllers (3 created)
- `apps/web/app/Http/Controllers/TournamentIndexController.php` — GET /tournaments listing; public+visible-status filter; cap-50 ordered by starts_at DESC.
- `apps/web/app/Http/Controllers/TournamentShowController.php` — GET /tournaments/{slug}; PublicTournamentData prop; abort(404) on private.
- `apps/web/app/Http/Controllers/TournamentPublicJsonController.php` — GET /tournaments/{slug}.json; ETag header + 304 short-circuit; throttle:60,1.

### Vue surface (8 created)
- `apps/web/resources/js/pages/Tournaments/Index.vue` — directory listing card per tournament.
- `apps/web/resources/js/pages/Tournaments/Show.vue` — 5-tab page (Overview / Bracket / Schedule / Standings / Participants); polled snapshot via `useTournamentPolling`.
- `apps/web/resources/js/components/tournaments/BracketCanvas.vue` — SVG renderer; Pattern 8 layout + Pitfall 9 stage-group offset.
- `apps/web/resources/js/components/tournaments/BracketNode.vue` — single bracket cell (rect + 2 participant rows).
- `apps/web/resources/js/components/tournaments/StandingsTable.vue` — format-aware tiebreak column (Buchholz / Point diff / generic).
- `apps/web/resources/js/components/tournaments/ParticipantsList.vue` — clan + seed + status rows.
- `apps/web/resources/js/components/tournaments/TournamentScheduleList.vue` — materialised brackets with /matches/{id} links.
- `apps/web/resources/js/composables/useTournamentPolling.ts` — 30s polling composable.

### Wiring (5 modified)
- `apps/web/routes/web.php` — 3 routes; .json declared before {slug}; slug constrained to `[A-Za-z0-9_-]+`.
- `apps/web/resources/js/layouts/PublicLayout.vue` — /tournaments nav link inserted between /matches and /players.
- `apps/web/lang/en/tournaments.php` — added `nav`, `directory`, `show`, `standings`, `participants` sub-groups.
- `apps/web/lang/en/common.php` — added `nav.tournaments` = 'Tournaments'.
- `apps/web/config/i18n.php` — `shared_namespaces` extended by `[matches, tournaments]` (Rule 3 — matches was missing too).
- `apps/web/resources/css/app.css` — 4 bracket palette CSS vars per theme.

### Tests (4 modified — 4 Wave 0 RED placeholders flipped to GREEN)
- `apps/web/tests/Feature/Tournaments/TournamentIndexPageTest.php` — 5 specs.
- `apps/web/tests/Feature/Tournaments/TournamentShowPageTest.php` — 4 specs.
- `apps/web/tests/Feature/Tournaments/TournamentPublicJsonControllerTest.php` — 7 specs.
- `apps/web/tests/Feature/Tournaments/TournamentEndToEndTest.php` — 2 specs (SC-1 capstone + public-surface render).

## Decisions Made

1. **Vue ambient typing via `App.Data.*` namespace.** Initial draft used `@trenchwars/shared-types` package imports (matching plan <interfaces> verbatim) but vue-tsc could not resolve the module — the package is consumed by `apps/bot` + `apps/rcon-worker` (out-of-process Node services), not by `apps/web`. The Phase 2/4 in-app idiom uses the ambient `App.Data.*` namespace declared in `apps/web/resources/js/types/api.d.ts` (regenerated by `typescript-transformer`). Switched all 7 Vue file imports to that idiom — outcome equivalent, vue-tsc clean.

2. **Route declaration order: `.json` BEFORE `{slug}`.** Laravel's first-match-wins dispatcher; absent ordering, `/tournaments/open-2026.json` would bind to `{tournament:slug}` with `slug=open-2026.json` and return 404. Additionally constrained the slug regex to `[A-Za-z0-9_-]+` so dotted slugs cannot shadow the .json endpoint either.

3. **Added `matches` to `shared_namespaces`.** Pre-existing gap surfaced as a blocker — Phase 4 plan 04-11 used `t('matches.directory.title')` etc. but never registered the namespace in `config/i18n.php`. Without registration the Vue `t()` returns the raw key. Rule 3 fix bundled with the tournaments registration to keep the public surface consistent.

4. **Bracket palette CSS vars per theme.** 4 new variables (`--color-bracket-winner-line`, `--color-bracket-loser-line`, `--color-bracket-node-completed`, `--color-bracket-node-pending`) defined on both `[data-theme="dark"]` and `[data-theme="light"]` blocks. Mirrors the Phase 1 60/30/10 palette pattern — no new design tokens, just slots in the existing palette.

5. **Capstone test walk algorithm.** Used an iterative outer loop with two queries per tick: (a) next undecided+materialised bracket with both participants known → create MatchResult (observer drives downstream); (b) if none, materialise any fully-populated downstream bracket and continue. 32-iteration safety brake (worst case for 8-clan single-elim is 7). The plan's pseudocode was unclear on round-2+ materialisation — `BracketMatchMaterialiserService::materialiseFirstRound` only handles round 1; downstream rounds need explicit `materialiseFor()` calls.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 — Blocking] Vue files imported `@trenchwars/shared-types` but the package is not resolvable from `apps/web`.**
- **Found during:** Task 2 (vue-tsc compile).
- **Issue:** Plan `<interfaces>` scaffold imports `import type { ... } from '@trenchwars/shared-types'`. vue-tsc errors: `Cannot find module '@trenchwars/shared-types' or its corresponding type declarations.` That package is consumed by sibling apps (bot, rcon-worker) via pnpm workspaces; `apps/web` reads the generated ambient namespace `App.Data.*` directly from `resources/js/types/api.d.ts`.
- **Fix:** Replaced 7 imports across 6 Vue files + 1 .ts composable with `type Foo = App.Data.Foo` aliases. Same runtime behaviour; same compile-time types.
- **Files modified:** `apps/web/resources/js/composables/useTournamentPolling.ts`, `apps/web/resources/js/components/tournaments/*.vue`, `apps/web/resources/js/pages/Tournaments/Show.vue`.
- **Verification:** `vue-tsc --noEmit` clean.
- **Committed in:** `4b44ec4` (Task 2).

**2. [Rule 3 — Blocking] `matches` and `tournaments` not in `config/i18n.php` shared_namespaces.**
- **Found during:** Task 2 (wiring tab labels).
- **Issue:** Vue `t('tournaments.tabs.bracket.label')` would return the raw key. Same gap applied to Phase 4's matches keys — pre-existing but exposed by the new surface area.
- **Fix:** Appended `'matches'` and `'tournaments'` to `shared_namespaces` in `apps/web/config/i18n.php`. The `HandleInertiaRequests::translations()` flat-merger picks them up automatically — no controller change needed.
- **Files modified:** `apps/web/config/i18n.php`.
- **Verification:** Manual — `t()` resolves namespaced keys in render output; NoHardcodedStringsTest stays green.
- **Committed in:** `4b44ec4` (Task 2).

**3. [Rule 2 — Missing Critical] Bracket SVG palette CSS variables missing.**
- **Found during:** Task 2 (BracketCanvas.vue authoring).
- **Issue:** Plan `<action>` referenced `var(--color-bracket-winner-line)` etc. but no such variables existed in `app.css`. Without them the SVG rendered with empty stroke fills.
- **Fix:** Added 4 variables on both `[data-theme="dark"]` and `[data-theme="light"]` blocks, picking values that fit the Phase 1 60/30/10 trench-military palette.
- **Files modified:** `apps/web/resources/css/app.css`.
- **Verification:** Manual render — SVG strokes + fills render correctly. Capstone test green.
- **Committed in:** `4b44ec4` (Task 2).

**4. [Rule 3 — Blocking] Route binding for slug + `.json` suffix.**
- **Found during:** Task 1 (route definition).
- **Issue:** Laravel's default URL parameter regex `[^/]+` matches dots, so `/tournaments/open-2026.json` was binding `{tournament:slug}` to the dotted string and looking up a non-existent tournament. The plan listed both routes under the same middleware group without precedence guidance.
- **Fix:** (a) Declared the `.json` route BEFORE the `{slug}` route — first-match-wins. (b) Constrained the `{slug}` parameter via `->where('tournament', '[A-Za-z0-9_-]+')` so dotted slugs can never shadow the .json endpoint.
- **Files modified:** `apps/web/routes/web.php`.
- **Verification:** All 7 JSON controller tests green; 4 Show tests green; manual `route:list --path=/tournaments` shows 3 routes registered with correct precedence.
- **Committed in:** `4e8d15b` (Task 1).

---

**Total deviations:** 4 auto-fixed (2 blocking, 1 missing critical, 1 blocking — all Rule 2/3).
**Impact on plan:** All four fixes were necessary for the implementation to compile and behave correctly. No scope creep — every fix was on the critical path of the plan's stated outputs.

## Issues Encountered

None. Tests passed on first run after Rule 3 fixes 1+2+3+4 were applied.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- **Plan 06-13 (i18n + audit + bot integration):** Ready to consume. The 4 RED Wave 0 stubs not yet flipped (`TournamentI18nKeyCoverageTest`, `TournamentAuditLogTest`, and 2 more for plan 06-14) are explicitly scoped to plan 06-13 + 06-14 — they remain RED by design.
- **Plan 06-14 (phase verification):** SC-1 capstone is GREEN end-to-end; SC-3 (public 5-tab page + 30s polling) wired. The verifier will check the remaining SCs (SC-2 admin actions land in 06-11; SC-4 audit lands in 06-13; SC-5 bot integration lands in 06-13).
- **Vue surface is reactive on the polled snapshot** — bracket completion / standings update will surface within 30s of an admin action without a page reload.

## Known Stubs

None — all sub-surfaces resolve real data:
- `Tournaments/Index.vue` reads the live `tournaments` prop populated by the controller (5 visibility tests assert filtering works).
- `Tournaments/Show.vue` reads the live `PublicTournamentData` first-paint prop and replaces it with the polled snapshot when the JSON endpoint returns 200.
- `BracketCanvas` / `StandingsTable` / `ParticipantsList` / `TournamentScheduleList` all read from the polled DTO; empty-state branches surface translated copy from `lang/en/tournaments.php`.

## Self-Check: PASSED

- [x] `apps/web/app/Http/Controllers/TournamentIndexController.php` exists
- [x] `apps/web/app/Http/Controllers/TournamentShowController.php` exists
- [x] `apps/web/app/Http/Controllers/TournamentPublicJsonController.php` exists
- [x] `apps/web/resources/js/pages/Tournaments/Index.vue` exists
- [x] `apps/web/resources/js/pages/Tournaments/Show.vue` exists
- [x] `apps/web/resources/js/components/tournaments/BracketCanvas.vue` exists
- [x] `apps/web/resources/js/components/tournaments/BracketNode.vue` exists
- [x] `apps/web/resources/js/components/tournaments/StandingsTable.vue` exists
- [x] `apps/web/resources/js/components/tournaments/ParticipantsList.vue` exists
- [x] `apps/web/resources/js/components/tournaments/TournamentScheduleList.vue` exists
- [x] `apps/web/resources/js/composables/useTournamentPolling.ts` exists
- [x] Commit `4e8d15b` (Task 1) exists in `git log --all`
- [x] Commit `4b44ec4` (Task 2) exists in `git log --all`
- [x] All 19 GREEN tests pass under `make pest` (TournamentIndexPageTest + TournamentShowPageTest + TournamentPublicJsonControllerTest + TournamentEndToEndTest + NoHardcodedStringsTest)
- [x] vue-tsc --noEmit clean
- [x] phpstan analyse clean
- [x] pint --test clean

---
*Phase: 06-tournaments-brackets*
*Completed: 2026-05-14*
