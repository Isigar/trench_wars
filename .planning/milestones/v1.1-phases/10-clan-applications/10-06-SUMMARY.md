---
phase: 10-clan-applications
plan: "06"
subsystem: clan-applications
tags: [controller, vue, inertia, tdd, eligibility-props, apply-form]
dependency_graph:
  requires:
    - ClanApplicationService::apply() + guards (plan 10-02)
    - clans.apply web route + ClanApplyController (plan 10-03)
    - ClanData.accepts_applications (plan 10-04)
    - clans.applications.apply_heading/apply_button/message_placeholder keys (plan 10-02)
    - clans namespace in shared_namespaces (config/i18n.php)
  provides:
    - ClanShowController: acceptsApplications + viewerIsActiveMember + viewerHasPendingApplication props
    - Clans/Show.vue: Apply-to-join form with showApplyBlock eligibility guard
    - ClanShowApplyTest: 9-case eligibility-matrix feature test
  affects:
    - apps/web/app/Http/Controllers/ClanShowController.php
    - apps/web/resources/js/pages/Clans/Show.vue
    - apps/web/tests/Feature/Clans/ClanShowApplyTest.php
tech_stack:
  added: []
  patterns:
    - TDD RED→GREEN for controller props (test before implementation)
    - page.props.auth direct access (NOT page.props.auth.user) — Inertia auth shared prop is user object or null
    - applyForm.errors cast via Record<string,string> for server-side ValidationException key not in useForm schema
    - clans namespace in shared_namespaces — t('clans.applications.*') resolves without controller-passed prop
key_files:
  created:
    - apps/web/tests/Feature/Clans/ClanShowApplyTest.php
  modified:
    - apps/web/app/Http/Controllers/ClanShowController.php
    - apps/web/resources/js/pages/Clans/Show.vue
decisions:
  - "clans namespace IS in shared_namespaces (config/i18n.php line 40) — t() path used directly; no applyStrings prop needed"
  - "applyForm.errors.application cast as Record<string,string> — ValidationException throws 'application' key not in useForm({ message }) schema; same pattern as Index.vue invited_user_id cast"
  - "showApplyBlock covers all four hide conditions: auth==null, !acceptsApplications, viewerIsActiveMember, viewerHasPendingApplication"
  - "Textarea label reuses apply_heading key for semantic label/for pairing (accessibility + VueFormLabelsTest)"
metrics:
  duration: "~12min"
  completed: "2026-06-04"
  tasks_completed: 2
  files_changed: 3
requirements: [CLAN-01]
---

# Phase 10 Plan 06: Apply-to-join Form + Viewer-State Props Summary

**`ClanShowController` adds three eligibility props (acceptsApplications / viewerIsActiveMember / viewerHasPendingApplication); `Clans/Show.vue` adds a `showApplyBlock`-gated Apply-to-join form posting to `clans.apply`; 9-case feature test covers the full eligibility matrix.**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-06-04
- **Completed:** 2026-06-04
- **Tasks:** 2 (Task 1: controller props + test; Task 2: Vue form)
- **Files modified:** 3

## Accomplishments

### Task 1 — ClanShowController viewer-state props (TDD)

Added three props to `ClanShowController::__invoke`:
- `$acceptsApplications = (bool) $clan->accepts_applications`
- `$viewerIsActiveMember = $viewer !== null && ClanMembership::where('user_id', $viewer->id)->whereNull('left_at')->exists()`
- `$viewerHasPendingApplication = $viewer !== null && ClanApplication::where('clan_id', $clan->id)->where('applicant_user_id', $viewer->id)->where('status', 'pending')->exists()`

All three added to the `Inertia::render('Clans/Show', [...])` array. `use App\Models\ClanApplication` import added.

**i18n delivery:** `clans` IS listed in `config/i18n.php` `shared_namespaces` (line 40 — added in Phase 9). The Vue page's existing `t('clans.*')` calls resolve via the shared translations prop. No `applyStrings` controller prop needed.

### Task 1 — ClanShowApplyTest.php

Nine Pest feature tests covering the full eligibility matrix:
1. Guest: open clan → accepts=true, member=false, pending=false
2. Guest: closed clan → accepts=false
3. Eligible authed viewer: all three props favorable
4. Active member of any clan → viewerIsActiveMember=true
5. Historical membership (left_at set) → viewerIsActiveMember=false
6. Pending application to this clan → viewerHasPendingApplication=true
7. Declined application → viewerHasPendingApplication=false (pending-only guard)
8. Cancelled application → viewerHasPendingApplication=false
9. Pending application to a different clan → viewerHasPendingApplication=false for target clan

### Task 2 — Apply-to-join form on Show.vue

- Added `useForm`, `usePage`, `Button`, `Textarea` imports
- Extended `defineProps` with three boolean viewer-state props
- `showApplyBlock` computed: `page.props.auth != null && acceptsApplications && !viewerIsActiveMember && !viewerHasPendingApplication`
  - Uses `page.props.auth` directly (NOT `.user`) — Inertia shares the user object or null
- `applyForm = useForm({ message: '' })` + `submitApplication()` posts to `route('clans.apply', clan.slug)` with `preserveScroll + onSuccess reset`
- Apply block template: heading (`t('clans.applications.apply_heading')`), Textarea (label + placeholder via t()), error slot for `applyForm.errors.application`, primary submit Button
- Error rendering: `(applyForm.errors as Record<string, string>).application` — server ValidationException uses key not in useForm schema

## Task Commits

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 RED | Failing ClanShowApplyTest | 05bfb61 | tests/Feature/Clans/ClanShowApplyTest.php |
| 1 GREEN | Controller props + pint fix | e96f4cf | ClanShowController.php + test (pint style fix) |
| 2 | Apply-to-join form on Show.vue | 118ac51 | resources/js/pages/Clans/Show.vue |

## Gate Results

| Gate | Result |
|------|--------|
| `make pest --filter=ClanShowApplyTest` | PASS (9 passed, 117 assertions) |
| `grep -c "viewerHasPendingApplication" ClanShowController.php` | 2 (compute + render) |
| `grep -c "route('clans.apply'"` Show.vue | 1 |
| `grep -c "page.props.auth.user"` Show.vue | 0 |
| `make pest --filter=NoHardcodedStrings` | PASS (1 passed) |
| `vue-tsc --noEmit` | PASS (no errors) |
| `make phpstan` L8 | PASS (No errors — 422 files) |
| `make pint --test` | PASS (663 files) |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Style] Pint auto-fix on ClanShowApplyTest.php**
- **Found during:** Task 1 GREEN pint --test gate
- **Issue:** `method_argument_space` + `method_chaining_indentation` violations in the new test file (multi-line assertInertia closures)
- **Fix:** Ran `pint tests/Feature/Clans/ClanShowApplyTest.php` inside the container
- **Files modified:** apps/web/tests/Feature/Clans/ClanShowApplyTest.php
- **Commit:** Included in e96f4cf

**2. [Rule 3 - Blocking] vue-tsc error on applyForm.errors.application**
- **Found during:** Task 2 vue-tsc gate
- **Issue:** `useForm({ message: '' })` generates typed `FormDataErrors<{ message: string }>` — `application` key does not exist on this type. The error key is injected by the server-side `ValidationException::withMessages(['application' => []])` outside the form schema.
- **Fix:** Cast `applyForm.errors as Record<string, string>` when accessing `.application` — same pattern as `Index.vue` L466 (`invited_user_id` cast)
- **Files modified:** apps/web/resources/js/pages/Clans/Show.vue
- **Commit:** Included in 118ac51

## Known Stubs

None — the Apply form is fully wired: props from controller, form posts to `clans.apply` (plan 10-03), server-side service validates (plan 10-02).

## Threat Flags

No new security surface beyond the plan's threat model:
- T-10-06-01 mitigated: showApplyBlock is UX-only; server-side service + web controller enforce all three guards.
- T-10-06-02 mitigated: viewerHasPendingApplication scoped to `applicant_user_id = $viewer->id` — never reveals other users' application state.

## Self-Check: PASSED

Files confirmed present:
- apps/web/app/Http/Controllers/ClanShowController.php — FOUND (modified)
- apps/web/resources/js/pages/Clans/Show.vue — FOUND (modified)
- apps/web/tests/Feature/Clans/ClanShowApplyTest.php — FOUND (created)

Commits confirmed:
- 05bfb61 — FOUND (RED test)
- e96f4cf — FOUND (GREEN controller)
- 118ac51 — FOUND (Vue form)
