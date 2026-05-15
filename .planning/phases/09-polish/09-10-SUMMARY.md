---
phase: 09-polish
plan: 10
subsystem: accessibility-wcag-2-1-aa-axe-core-focus-visible
tags: [wave-8, accessibility, a11y, wcag-2-1-aa, axe-core, focus-visible, sc-5, pitfall-11, ci-workflow, pending-manual-smoke, d-013]
requires:
  - "Phase 1 — Tailwind v4 CSS-first @theme tokens (--color-focus-ring canonical, plan 01-07)"
  - "Phase 1 — PublicLayout SkipToContent link with t('common.actions.skip_to_content') + <main id=\"main\"> anchor (plan 01-07 + plan 05-10 t() refactor)"
  - "Phase 1 — Root Blade <html lang=\"{{ str_replace('_','-',app()->getLocale()) }}\"> + Inertia v2 (plan 01-06)"
  - "Phase 1 — NoHardcodedStringsTest (plan 01-08) — kept GREEN by this plan"
  - "Phase 9 plan 09-01 — Wave 0 stubs (tests/Feature/A11y/PublicPagesHtmlLangTest.php + tests/Feature/A11y/VueFormLabelsTest.php) turned GREEN by this plan"
  - "Public route surface from Phase 2/4/6/7/9 — / + /clans + /matches + /tournaments + /blog + /events + /leaderboards (the axe-scan + Pest matrix)"
provides:
  - "apps/web/resources/css/app.css — global `*:focus-visible { outline: 2px solid var(--color-focus-ring); outline-offset: 2px; border-radius: 2px; }` PLUS enhanced 4 px outer ring on `button:focus-visible, a:focus-visible, [role=\"button\"]:focus-visible` via color-mix(in srgb, var(--color-focus-ring) 30%, transparent)"
  - ".github/workflows/a11y.yml — @axe-core/cli@^4.11.3 GitHub Actions workflow (push to main/develop/master + pull_request + workflow_dispatch); spins docker compose stack, polls http://localhost:8000/ for ≤120 s, runs axe with --tags wcag2aa,wcag21aa --exit --reporter v2 against the 7-URL public route matrix (Pitfall 11: admin/auth routes NEVER scanned); uploads axe-*.json artifacts ONLY on failure (T-09-10-01 mitigation); per-step timeout-minutes bound (T-09-10-02 mitigation)"
  - "apps/web/tests/Feature/A11y/PublicPagesHtmlLangTest.php — 7 GREEN tests (Wave 0 stub → GREEN): asserts <html lang=\"en\"> on /, /clans, /matches, /tournaments, /blog, /events, /leaderboards (route surface matches axe matrix verbatim)"
  - "apps/web/tests/Feature/A11y/VueFormLabelsTest.php — 1 GREEN static-scan test (Wave 0 stub → GREEN): walks apps/web/resources/js/{pages,layouts,components}, strips <template> body + HTML comments, regexes lowercase <input>/<textarea>/<select> opening tags, asserts each has aria-label / aria-labelledby / matching <label for=\"X\"> / matching <label :for=\"dynamicId\"> / implicit-label wrapper; 0 violations against the current Vue surface"
artifacts:
  - "PublicLayout SkipToContent + <main id=\"main\"> verified intact (no edits required — already wired from Phase 1)"
  - "axe-core CI workflow gates merges on WCAG 2.1 AA compliance for the public route surface"
  - "Pre-flight + nginx-log-dump steps in the workflow surface deploy/migration regressions before the axe scan window"
  - "PENDING_MANUAL_SMOKE handoff: operator-driven keyboard navigation walkthrough deferred per autonomous workflow convention (matches Phase 1/2/3/4/5/6/7/8 closing pattern)"
affects:
  - "Every focusable element on every Vue surface now renders a 2 px outline + 2 px offset focus ring via :focus-visible (only on keyboard focus; mouse :focus suppressed by browser :focus-visible heuristic). Buttons / anchors / role=button widgets gain an additional 4 px outer ring for dense-layout perceptibility. The pre-existing :where(button,a,input,select,textarea,[tabindex]):focus-visible rule from Phase 1 is preserved AND the new global *:focus-visible catches SVG <g role=\"button\"> + tabindex elements that bypass the static selector list."
  - "axe-core CI now runs on every push to main/develop/master + every pull_request — failing violations BLOCK merge. Pitfall 11 enforced: admin / auth-gated routes (/admin, /login, /auth/*, /notifications, /account/*) are NOT in the route matrix, so axe never scans the login-redirect HTML."
  - "Future plans adding new public routes MUST extend BOTH (a) the URL matrix in .github/workflows/a11y.yml AND (b) the test matrix in tests/Feature/A11y/PublicPagesHtmlLangTest.php. The two matrices are documented inline to stay in lock-step."
  - "Future plans adding new Vue form controls (input/textarea/select) MUST provide one of: <label for>, aria-label, aria-labelledby, or implicit-label wrapper. VueFormLabelsTest enforces this on every test run (static scan, ~50 ms — no perf impact)."
  - "PENDING_MANUAL_SMOKE: operator owns the keyboard-nav checkpoint walkthrough out-of-band. Same close pattern as 08-PHASE-VERIFICATION.md PENDING_MANUAL_SMOKE A-D operator items. Recorded in the Operator Handoff section below."
tech-stack:
  added:
    - "@axe-core/cli@^4.11.3 (CI-only — installed via npm install -g in the workflow; NOT a package.json dependency)"
  patterns:
    - "Pitfall 11 LOCKED — axe-core scans ONLY unauthenticated public routes; admin + auth routes are excluded. Comment in YAML head documents the constraint and lists the 7-URL allowlist."
    - "T-09-10-01 mitigation — `if: failure()` gate on actions/upload-artifact@v4 so passing runs do not retain DOM snapshots. Retention capped at 14 days."
    - "T-09-10-02 mitigation — `--exit` flag on axe returns non-zero on first violation per URL; `set +e` + EXIT accumulator in the bash loop ensures every URL is scanned even after a failure (collects every report on a single CI run rather than fail-fast); per-step `timeout-minutes` caps each scan window."
    - "Route-matrix lock-step — the URL list in .github/workflows/a11y.yml MUST match tests/Feature/A11y/PublicPagesHtmlLangTest.php verbatim. The Pest test acts as a CI canary if a route is added in code but forgotten in the axe matrix."
    - "Case-sensitive HTML-tag scan in VueFormLabelsTest — only lowercase <input>/<textarea>/<select> are AT-announced native form controls. PascalCase Vue wrappers (<Select>, <TextInput>, <Textarea>) are audited at their wrapper definition (apps/web/resources/js/components/ui/{Select,TextInput,Textarea}.vue — all three emit native <label :for=\"id\"> paired with the underlying native control), so a parent passing `:label=\"...\"` always renders a labelled native element in the final DOM."
    - "Plan literal `/articles` → corrected to `/blog` in BOTH the axe workflow and the Pest matrix. Phase 7 plan 07-09 LOCKED the public-facing slug as `/blog` (Inertia component name is `Articles/Index` but the route is `/blog`)."
    - "Plan literal `--color-focus` → resolved to `--color-focus-ring` (the actual Phase 1 canonical token; 50+ Vue references across the codebase). New global *:focus-visible rule + interactive-primitive shadow rule both bind to `var(--color-focus-ring)`. Rule 1 deviation — aligned with on-disk reality, no Phase-1-era token rename undertaken in this plan."
    - "color-mix() polyfill not required — Tailwind v4 CSS targets baseline modern browsers (Chrome 111+, Firefox 113+, Safari 16.2+) which all support `color-mix(in srgb, ...)`. The 30% alpha outer ring degrades gracefully (zero shadow) on the small browser tail below those versions."
    - "PENDING_MANUAL_SMOKE handoff — autonomous workflow defers operator-driven manual checkpoints to an out-of-band walkthrough, recorded in this SUMMARY's Operator Handoff section. Matches Phase 1/2/3/4/5/6/7/8 close pattern. The Task 2 checkpoint:human-verify gate is converted to an operator deliverable; no code is blocked on the manual step."
key-files:
  created:
    - ".github/workflows/a11y.yml — 106 lines; NEW @axe-core/cli CI workflow with full Pitfall 11 + threat-model commentary in the YAML head"
  modified:
    - "apps/web/resources/css/app.css — appended 20 lines (global *:focus-visible rule + button/a/role=button enhanced shadow rule); pre-existing :where(...):focus-visible rule preserved unchanged"
    - "apps/web/tests/Feature/A11y/PublicPagesHtmlLangTest.php — Wave 0 stub → 7 GREEN tests (1 per public route); +82 lines"
    - "apps/web/tests/Feature/A11y/VueFormLabelsTest.php — Wave 0 stub → 1 GREEN static-scan test; +148 lines (regex-based scanner with implicit-label wrapper handling)"
  verified-intact:
    - "apps/web/resources/js/layouts/PublicLayout.vue — line 41 `<a href=\"#main\" class=\"skip-link\">{{ t('common.actions.skip_to_content') }}</a>` + line 136 `<main id=\"main\" class=\"flex-1\">` — verified during execution, no edits required"
decisions:
  - "D-09-10-A — Plan literal `--color-focus` token corrected to `--color-focus-ring`. Phase 1 plan 01-07 LOCKED the canonical Tailwind v4 @theme token name as `--color-focus-ring` (declared on `:root,[data-theme=dark]` line 51 and on `[data-theme=light]` line 72 of apps/web/resources/css/app.css). 50+ Vue references across the codebase resolve `var(--color-focus-ring)`. Renaming to match the plan literal would have required a sweeping rename across 50+ files outside the plan's <files> list. Rule 1 deviation — aligned with on-disk reality, same pattern as 09-02 D-09-02-A / 09-03 D-09-03-B."
  - "D-09-10-B — Plan literal `/articles` corrected to `/blog` across BOTH the axe-core URL matrix in .github/workflows/a11y.yml AND the Pest route matrix in PublicPagesHtmlLangTest. Phase 7 plan 07-09 LOCKED the public-facing route as `/blog` (the Inertia component name is `Articles/Index` but the route registered in apps/web/routes/web.php is `/blog`). The two matrices are documented inline to stay in lock-step."
  - "D-09-10-C — VueFormLabelsTest scans ONLY lowercase native HTML form controls (<input>, <textarea>, <select>). PascalCase Vue wrapper components (<Select>, <TextInput>, <Textarea> under apps/web/resources/js/components/ui/) are NOT scanned at the call site because they emit native `<label :for=\"id\">` paired with the underlying native control INSIDE the wrapper definition. Auditing at the wrapper definition catches every caller automatically — a parent passing `:label=\"Email\"` always renders a labelled native <input> in the final DOM. Case-sensitive regex enforces this scope; if a Vue caller emitted a raw native <input> without a label, the static scan would catch it on next CI run."
  - "D-09-10-D — axe artifact upload gated on `if: failure()` ONLY. T-09-10-01 disposition: mitigate. Passing CI runs do not retain DOM snapshots (which could leak rendered user-content, even on public surfaces). Failing runs retain reports for 14 days for triage. Operator can workflow_dispatch + always-upload manually for one-off diagnostic runs without modifying the workflow."
  - "D-09-10-E — Axe per-URL loop uses `set +e` + EXIT accumulator rather than fail-fast. Rationale: collecting EVERY violation report on a single CI run beats fail-fast for triage UX. Fixing a single violation and re-running CI to discover the next one is wasted CI minutes; the accumulator approach surfaces all 7 reports in one workflow run so the operator can prioritise."
  - "D-09-10-F — Task 2 (checkpoint:human-verify — manual keyboard navigation smoke) DEFERRED to PENDING_MANUAL_SMOKE operator handoff per autonomous workflow convention. The checkpoint:human-verify gate is converted into the Operator Handoff section below; the operator walks the 10-step keyboard-nav checklist out-of-band and reports back via the standard Phase 9 PENDING_MANUAL_SMOKE channel. Same close pattern as Phase 1/2/3/4/5/6/7/8. Task 1 (CSS + axe-core CI + 2 Pest tests GREEN) is committed and verified."
metrics:
  duration_seconds: 780
  duration_human: "~13m"
  duration_includes_checkpoint_pause: false
  completed_at: "2026-05-15T15:57:00Z"
  files_created: 1
  files_modified: 3
  files_verified_intact: 1
  total_files: 4
  ci_workflows_added: 1
  pest_tests_added: 8
  pest_assertions_added: 15
  test_files_wave_0_to_green: 2
  wave_0_stubs_turned_green: 2
  tasks_committed: 1
  tasks_deferred: 1
  tasks_deferred_reason: "PENDING_MANUAL_SMOKE — autonomous workflow defers operator-driven keyboard-nav smoke"
  pint_files_passed: 0
  pint_dirty_status: "no dirty PHP files (CSS + YAML + already-Pint-clean Pest stubs)"
  phpstan_errors: 0
  test_run_duration_seconds: 2.08
  filter_run_tests_passed: 8
  filter_run_assertions: 15
  axe_route_matrix_size: 7
  axe_route_list: ["/", "/clans", "/matches", "/tournaments", "/blog", "/events", "/leaderboards"]
  excluded_admin_routes_count: 5
  excluded_admin_routes: ["/admin/*", "/login", "/auth/*", "/notifications", "/account/*"]
  lines_added_approx: 376
---

# Phase 9 Plan 10: SC-5 Accessibility (WCAG 2.1 AA) Summary

Shipped the SC-5 accessibility round-1 deliverable. Wired a site-wide `:focus-visible` rule using the Phase 1 canonical `--color-focus-ring` token, authored a new GitHub Actions `a11y` workflow that runs `@axe-core/cli@^4.11.3` against a Pitfall-11-compliant public-route allowlist, and turned two Wave 0 Pest stubs GREEN: `PublicPagesHtmlLangTest` (7 routes) + `VueFormLabelsTest` (static-scan of every Vue form control). Task 2 (manual keyboard-nav smoke) is recorded as PENDING_MANUAL_SMOKE per the autonomous workflow convention — operator walks the 10-step checklist out-of-band.

## What Landed (Task 1 — GREEN)

### CSS — site-wide focus rings via Tailwind v4 @theme tokens

`apps/web/resources/css/app.css` gains a 20-line append (after the pre-existing Phase 1 `:where(...):focus-visible` block, which is preserved unchanged):

```css
/* SC-5 a11y global focus-visible — Phase 9 plan 09-10.
   Catches anything not covered by the :where(...) list above (e.g. SVG <g role="button">,
   custom Vue elements with tabindex emitted via templates that bypass the static selector).
   Uses --color-focus-ring (Phase 1 canonical token; plan 09-10 deviation: plan literal said
   --color-focus but Phase 1 standardised on --color-focus-ring across 50+ Vue references). */
*:focus-visible {
    outline: 2px solid var(--color-focus-ring);
    outline-offset: 2px;
    border-radius: 2px;
}

/* Enhanced focus shadow for interactive primitives (buttons + links + role="button"
   custom widgets). 4px outer ring at 30 % alpha — improves perceptibility over the
   bare 2px outline on dense layouts without overwhelming non-interactive focus. */
button:focus-visible,
a:focus-visible,
[role="button"]:focus-visible {
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-focus-ring) 30%, transparent);
}
```

Token binding:

| Theme | `--color-focus-ring` | Contrast vs `--color-bg` (visual) |
|-------|----------------------|-----------------------------------|
| Dark  | `#C7A23A`            | High contrast against `#1A1B16`   |
| Light | `#6B5210`            | High contrast against `#F5F2E6`   |

Both values originate from UI-SPEC.md § Color (60/30/10 trench-military palette) and were LOCKED in Phase 1 plan 01-07. WCAG 2.1 AA non-text contrast (1.4.11 — 3:1 minimum) is comfortably met on both themes.

### axe-core CI workflow — Pitfall-11-compliant public-only scan

`.github/workflows/a11y.yml` is a new 106-line workflow. Header documents Pitfall 11 + T-09-10-01 + T-09-10-02 mitigations inline. The job spins the docker compose stack (`docker compose up -d --build`), polls `http://localhost:8000/` for up to 120 s, then iterates the public-route matrix:

```bash
for url in / /clans /matches /tournaments /blog /events /leaderboards; do
  axe "http://localhost:8000${url}" \
    --tags wcag2aa,wcag21aa \
    --exit \
    --reporter v2 \
    > "axe-${slug}.json"
done
```

`set +e` + an EXIT accumulator means every URL is scanned on every run — fail-fast was rejected for triage UX (D-09-10-E). Reports are uploaded on `if: failure()` only (T-09-10-01) with 14-day retention.

#### Explicit exclusions (Pitfall 11)

| Excluded prefix | Reason |
|-----------------|--------|
| `/admin/*` | Auth-gated Filament panel; axe would scan the login redirect |
| `/login`, `/auth/*` | Auth flow; redirects to Discord OAuth (off-network) |
| `/notifications`, `/account/*` | Per-user gated surfaces (Phase 9 plan 09-06) |

### PublicPagesHtmlLangTest — 7 GREEN tests

`apps/web/tests/Feature/A11y/PublicPagesHtmlLangTest.php` was a Wave 0 stub from plan 09-01; this plan brings it to 7 GREEN tests asserting `<html lang="en">` on every route in the public matrix. Test surface matches the axe matrix verbatim (lock-step documented in the file header).

### VueFormLabelsTest — 1 GREEN static-scan test, 0 violations

`apps/web/tests/Feature/A11y/VueFormLabelsTest.php` was a Wave 0 stub; this plan brings it to 1 GREEN static-scan test (148 lines). Walks `apps/web/resources/js/{pages,layouts,components}`, strips `<template>` body + HTML comments, regexes lowercase `<input>/<textarea>/<select>` opening tags, exempts hidden/submit/reset/button/image input types, and asserts each control has at least one of:

1. `aria-label="..."` or `:aria-label="..."`
2. `aria-labelledby="..."` or `:aria-labelledby="..."`
3. `id="X"` + sibling `<label for="X">` (static pair)
4. `:id="dynamicId"` + sibling `<label :for="dynamicId">` (dynamic pair)
5. Implicit-label wrapper (control is a descendant of `<label>` per HTML spec 4.10.4)

Current Vue surface: **0 violations**. PascalCase Vue wrapper components (`<Select>`, `<TextInput>`, `<Textarea>`) are deliberately NOT scanned at call sites because their definitions in `apps/web/resources/js/components/ui/` emit native `<label :for="id">` paired with the underlying native control — auditing the wrapper definition catches every caller automatically.

### PublicLayout SkipToContent — verified intact (no edits required)

`apps/web/resources/js/layouts/PublicLayout.vue` was inspected:

- Line 41: `<a href="#main" class="skip-link">{{ t('common.actions.skip_to_content') }}</a>`
- Line 136: `<main id="main" class="flex-1">`

Wired correctly from Phase 1; the `.skip-link` class in `apps/web/resources/css/app.css` (lines 130-143) renders it visually-hidden until focused (`position: absolute; left: -10000px; ... :focus { left: 8px; top: 8px; z-index: 9999; }`). No edits required.

## Verification

```bash
$ docker compose exec -T web ./vendor/bin/pest --filter="PublicPagesHtmlLangTest|VueFormLabelsTest" --no-coverage
PASS  Tests\Feature\A11y\PublicPagesHtmlLangTest
  ✓ renders <html lang="en"> on / (homepage)
  ✓ renders <html lang="en"> on /clans (directory)
  ✓ renders <html lang="en"> on /matches (calendar)
  ✓ renders <html lang="en"> on /tournaments (directory)
  ✓ renders <html lang="en"> on /blog (articles index)
  ✓ renders <html lang="en"> on /events (calendar)
  ✓ renders <html lang="en"> on /leaderboards (index)
PASS  Tests\Feature\A11y\VueFormLabelsTest
  ✓ every Vue form input has an associated label or aria-label (static scan)
Tests:    8 passed (15 assertions)
Duration: 2.08s

$ docker compose exec -T web ./vendor/bin/pint --test --dirty
PASS   ........................................................... 0 files

$ docker compose exec -T web ./vendor/bin/phpstan analyse --no-progress --memory-limit=2G
[OK] No errors
```

Quality gates: GREEN.

## Operator Handoff — PENDING_MANUAL_SMOKE (Task 2 — Manual Keyboard Navigation)

> **Status:** PENDING_MANUAL_SMOKE. Same close pattern as 08-PHASE-VERIFICATION.md operator items A-D. The autonomous executor records the checklist here; the operator walks it out-of-band and reports back via the standard Phase 9 channel. Task 2 (`checkpoint:human-verify`) is converted to this deferred deliverable.

### Pre-walk setup

1. `make up` — start the local stack (web + nginx + ssr + postgres + redis + worker).
2. Open `http://localhost:8000` in a browser. Hide the mouse / use Tab-only input.
3. Open DevTools → Accessibility tree as a sanity reference.

### 10-step checklist (from RESEARCH "Manual keyboard test checklist")

| # | Surface | Action | Expected outcome |
|---|---------|--------|------------------|
| 1 | Top nav | `Tab` through every link | Each link receives a visible 2 px focus ring; `Enter` navigates |
| 2 | Theme toggle | `Tab` to the toggle, press `Space` | Light/dark theme flips; `aria-pressed` attribute updates |
| 3 | Mobile menu (resize to <768px) | `Tab` into hamburger, then through opened menu | Menu opens on `Enter`; `Tab` cycles INSIDE the menu; `Esc` closes it; focus RETURNS to the hamburger button |
| 4 | LoginButton | `Tab` to login CTA, press `Enter` | Redirects to Discord OAuth |
| 5 | User menu (after login) | `Tab` to user avatar, press `Enter`, then `Esc` | Menu opens; `Esc` closes it; focus returns to the avatar trigger |
| 6 | Notifications bell | `Tab` to bell, `Enter` to open, arrow keys to cycle, `Enter` on item to mark read, `Esc` to close | Drawer opens; arrow keys cycle items; item action fires on `Enter`; `Esc` closes drawer; focus returns to bell trigger |
| 7 | Notification preferences (`/account/notification-preferences`) | `Tab` through the 5×2 switch matrix, `Space` to toggle each | Every switch is reachable; `Space` toggles; visible focus ring on every switch |
| 8 | Match signup modal (`/matches/{id}` with open signups) | `Tab` opens modal, `Tab` cycles slots, `Enter` selects, `Esc` cancels | Modal traps focus; `Tab` cycles slot list; `Enter` confirms a slot; `Esc` cancels and restores focus to trigger |
| 9 | Article reader (`/blog/{any}`) | `Tab` through headings + links in document order | Logical reading order; no focus skips |
| 10 | Filament admin BulkActions (`/admin` as admin user) | Select 2 users with `Space` on checkboxes, open BulkAction menu with `Enter`, `Tab` cycles inside modal, `Esc` closes | Checkboxes toggle on `Space`; BulkAction modal opens and traps focus; `Tab` cycles modal options; `Esc` closes; focus returns to BulkAction trigger |

### Acceptance criteria

- Every focusable element renders a 2 px outline + 2 px offset focus ring (buttons / anchors / `role="button"` additionally show the 4 px outer color-mix shadow).
- No focus traps EXCEPT the deliberate modal/drawer ones (steps 3, 6, 8, 10).
- No focus skips (focus always lands on the next AT-announced element in DOM order).
- All `Esc` closures restore focus to the triggering element.

### Reporting

After the walkthrough:

- All 10 steps pass → reply `approved` in the standard PENDING_MANUAL_SMOKE channel; close-out of Phase 9 plan 09-12 [BLOCKING] can record the smoke as GREEN.
- Any step fails → reply with: surface name + step number + observed behaviour + expected behaviour. The failure becomes a new Phase 9 plan (or a Phase 9 plan 09-12 amendment) before the [BLOCKING] gate closes.

## Deviations from Plan

### Rule 1 Deviations (auto-fix — aligned with on-disk reality)

1. **D-09-10-A — `--color-focus` → `--color-focus-ring` token name**
   - **Found during:** Task 1 CSS authoring.
   - **Issue:** Plan literal referenced `--color-focus` but Phase 1 plan 01-07 LOCKED the canonical Tailwind v4 @theme token name as `--color-focus-ring`. 50+ Vue references resolve `var(--color-focus-ring)`. Renaming would have required a sweeping refactor outside this plan's `<files>` list.
   - **Fix:** Bound the new `*:focus-visible` + `button/a/[role=button]:focus-visible` rules to `var(--color-focus-ring)` instead. Documented inline in the CSS comment.
   - **Files modified:** `apps/web/resources/css/app.css`.
   - **Commit:** 01abd1e.

2. **D-09-10-B — `/articles` → `/blog` route correction**
   - **Found during:** Task 1 axe workflow + PublicPagesHtmlLangTest authoring.
   - **Issue:** Plan literal listed `/articles` in the public route matrix but Phase 7 plan 07-09 LOCKED the public-facing route as `/blog` (the Inertia component name is `Articles/Index` but the route registered in `apps/web/routes/web.php` is `/blog`).
   - **Fix:** Used `/blog` in BOTH the axe URL matrix in `.github/workflows/a11y.yml` AND the Pest route matrix in `PublicPagesHtmlLangTest`. Documented the correction inline in both files.
   - **Files modified:** `.github/workflows/a11y.yml`, `apps/web/tests/Feature/A11y/PublicPagesHtmlLangTest.php`.
   - **Commit:** 01abd1e.

### Rule 4 Deferral (autonomous workflow convention)

3. **D-09-10-F — Task 2 (manual keyboard nav smoke) → PENDING_MANUAL_SMOKE**
   - **Trigger:** Task 2 is `type="checkpoint:human-verify"` with a 10-step manual keyboard checklist that requires an operator at a real browser.
   - **Disposition:** Deferred to PENDING_MANUAL_SMOKE per autonomous workflow convention (same close pattern as Phase 1/2/3/4/5/6/7/8). The checkpoint:human-verify gate is converted to the **Operator Handoff** section above; the operator walks the 10-step checklist out-of-band and reports back via the standard Phase 9 channel. Task 1 (CSS + axe-core CI + 2 Pest tests) is committed and verified GREEN.
   - **Not a Rule 4 architectural deviation** — automation cannot drive a real-browser keyboard smoke without a Playwright/WebDriver harness, which is out of scope for the round-1 polish phase. The axe-core CI workflow already covers the automatable subset (contrast + ARIA + landmarks); the manual smoke covers focus traps + tab order which axe can't detect.

## Threat Model — Disposition Verified

| Threat ID | Category | Component | Plan Disposition | Implementation Status |
|-----------|----------|-----------|------------------|----------------------|
| T-09-10-01 | I (Info Disclosure) | axe-core CI artifact leaks DOM contents | accept (per plan) — UPGRADED to mitigate | `if: failure()` gate on artifact upload + 14-day retention cap; passing runs retain no DOM snapshots |
| T-09-10-02 | D (DoS) | axe scan retries hang CI runner | mitigate | `--exit` flag + per-step `timeout-minutes: 10` + 120 s docker-compose-up bounded wait |
| T-09-10-03 | T (Tampering) | Manual keyboard checkpoint skipped or rubber-stamped | accept | Reviewer-discipline issue. The autonomous workflow surfaces the checklist verbatim in the Operator Handoff section above; PENDING_MANUAL_SMOKE channel record forms the audit trail |
| T-09-10-04 | I (Info Disclosure) | Pitfall 11 — axe scanning admin routes | mitigate | Workflow enumerates the 7-URL public allowlist explicitly + comment in YAML head documents the constraint + excluded list of admin/auth prefixes |

T-09-10-01 was upgraded from `accept` (plan) to `mitigate` (implementation) by gating artifact upload on `if: failure()` — Rule 2 (auto-add missing critical functionality: security improvement) at zero implementation cost.

## Quality Gates

| Gate | Status |
|------|--------|
| Pest filter `PublicPagesHtmlLangTest\|VueFormLabelsTest` | **8 passed (15 assertions) — 2.08 s** |
| Pint `--test --dirty` | **PASS — 0 dirty files** |
| PHPStan L8 analyse | **OK — No errors** |
| `.github/workflows/a11y.yml` exists | **PASS** |
| `apps/web/resources/css/app.css` contains `:focus-visible` | **PASS — 3 occurrences (pre-existing :where + new global + new shadow)** |
| Pre-existing `NoHardcodedStringsTest` | **Untouched (no new hardcoded strings introduced)** |
| Pre-existing focus-ring `:where(...)` rule | **PRESERVED unchanged** |
| PublicLayout SkipToContent + `<main id="main">` | **VERIFIED intact (no edits)** |
| Task 2 PENDING_MANUAL_SMOKE recorded | **DONE — Operator Handoff section above** |

## Self-Check

- **CSS edit committed:** `apps/web/resources/css/app.css` (commit `01abd1e`) — file contains `*:focus-visible` and `button:focus-visible` per the implemented diff above.
- **Workflow file committed:** `.github/workflows/a11y.yml` (commit `01abd1e`) — file exists; contains the `@axe-core/cli` install + the 7-URL public matrix + Pitfall 11 commentary.
- **Pest tests GREEN:** 8 passed (15 assertions) under `PublicPagesHtmlLangTest|VueFormLabelsTest`.
- **Pint:** PASS (0 dirty files).
- **PHPStan:** OK (0 errors).
- **Task 1 commit hash:** `01abd1e` — verified in `git log --oneline -1 01abd1e`.
- **PublicLayout SkipToContent:** verified intact (no edits required; commit unchanged).
- **Task 2:** PENDING_MANUAL_SMOKE — operator handoff recorded above; no commit required for a deferred manual deliverable.

## Self-Check: PASSED
