---
phase: 10
fixed_at: "2026-06-04T09:55:00Z"
review_path: .planning/phases/10-clan-applications/10-REVIEW.md
iteration: 1
findings_in_scope: 6
fixed: 6
skipped: 0
status: all_fixed
---

# Phase 10: Code Review Fix Report

**Fixed at:** 2026-06-04T09:55:00Z
**Source review:** `.planning/phases/10-clan-applications/10-REVIEW.md`
**Iteration:** 1

**Summary:**
- Findings in scope: 6
- Fixed: 6
- Skipped: 0

## Fixed Issues

### BL-01 + BL-02: apply() race → 500 and applications to inactive clans

**Files modified:** `apps/web/app/Services/ClanApplicationService.php`, `apps/web/tests/Feature/Clans/ClanApplyServiceTest.php`, `apps/web/tests/Feature/Bot/BotApiClanApplicationTest.php`
**Commit:** `2a764d8`
**Applied fix:**

BL-02 (inactive clan): Added Guard 0 to `ClanApplicationService::apply()` — checks `$clan->status !== 'active'` and throws `ClanNotRecruitingException` before any other eligibility check. This blocks both web (POST /clans/{slug}/apply) and bot API surfaces that previously had no clan-status guard.

BL-01 (race condition): Wrapped `ClanApplication::create()` in a `try/catch (UniqueConstraintViolationException)` block that re-throws as `DuplicateApplicationException`. Concurrent requests that both pass Guard 3 (SELECT EXISTS) before either commits now receive a proper 422 instead of an unhandled 500.

Also added `use Illuminate\Database\UniqueConstraintViolationException` import to the service.

Tests added:
- `apply() throws ClanNotRecruitingException when clan is suspended (BL-02)`
- `apply() throws ClanNotRecruitingException when clan is disbanded (BL-02)`
- `apply() throws DuplicateApplicationException when DB already has pending row before the call (BL-01 via Guard 3)`
- `partial unique index on clan_applications raises UniqueConstraintViolationException for concurrent pending inserts (BL-01 index defence)` — uses a savepoint (nested DB::transaction) to avoid aborting the outer RefreshDatabase transaction on Postgres
- `BotApiClanApplicationTest: returns 422 clan_not_recruiting when clan is suspended even if accepts_applications=true (BL-02)`

---

### WR-02: unbounded message field

**Files modified:** `apps/web/app/Http/Controllers/Clans/ClanApplyController.php`, `apps/web/tests/Feature/Clans/ClanApplyWebTest.php`
**Commit:** `dbea099`
**Applied fix:** Added `$request->validate(['message' => ['nullable', 'string', 'max:2000']])` as the first statement in `ClanApplyController::store()` before any service delegation. Over-long messages now return a 422 with an error on the `message` key.

Tests added:
- `message longer than 2000 characters returns validation error on message key — no row created (WR-02)`
- `applying to a suspended clan returns session error and no row is created (BL-02)` (controller surface)
- `applying to a disbanded clan returns session error and no row is created (BL-02)` (controller surface)

---

### WR-03: no rate-limit on web apply route

**Files modified:** `apps/web/app/Providers/AppServiceProvider.php`, `apps/web/routes/web.php`
**Commit:** `1fd6168`
**Applied fix:** Registered `clan-apply` RateLimiter in `AppServiceProvider::boot()` — 5 requests per minute per authenticated user, with an IP-key fallback for defence-in-depth. Added `->middleware('throttle:clan-apply')` to the `POST /clans/{clan:slug}/apply` route definition. Pattern mirrors the existing `notifications-read` / `report-abuse` limiters from plan 09-11.

---

### WR-01: wrong name in accept() flash

**Files modified:** `apps/web/app/Http/Controllers/MyClan/ClanApplicationController.php`, `apps/web/tests/Feature/Clans/ClanApplicationTest.php`
**Commit:** `b9d34e6`
**Applied fix:** Changed the `name` parameter passed to `clans.applications.accepted` from `$actor->username` (the acceptor) to `$application->applicant->username ?? (string) $application->applicant_user_id` (the applicant). The `applicant` BelongsTo relation already exists on `ClanApplication`.

Tests added:
- `accept() flash message contains the applicant username, not the acceptor username (WR-01)`

---

### IN-01: textarea label duplicates heading

**Files modified:** `apps/web/lang/en/clans.php`, `apps/web/resources/js/pages/Clans/Show.vue`
**Commit:** `83bcaaa`
**Applied fix:** Added `'message_label' => 'Cover message (optional)'` under the `applications` array in `lang/en/clans.php`. Updated `Clans/Show.vue` to use `t('clans.applications.message_label')` as the Textarea `:label` instead of reusing `t('clans.applications.apply_heading')`.

---

### Pint style fix (post-fix cleanup)

**Files modified:** `apps/web/tests/Feature/Clans/ClanApplyServiceTest.php`
**Commit:** `03d0ad3`
**Applied fix:** Pint auto-fixed `fully_qualified_strict_types` rule — hoisted `\Illuminate\Database\UniqueConstraintViolationException`, `\Illuminate\Support\Facades\DB`, and `\Illuminate\Support\Str` from inline FQCNs to `use` imports at the top of the test file.

---

## Skipped Issues

None — all in-scope findings were fixed.

---

## Gate Results

| Gate | Result | Detail |
|------|--------|--------|
| `make pest` (full suite) | PASS | 1344 passed, 4746 assertions |
| `make pest` (targeted) | PASS | 52 passed, 239 assertions |
| `make phpstan` | PASS | No errors (422 files analysed) |
| `make pint --test` | PASS | 663 files, 0 style issues |
| `vue-tsc --noEmit` | PASS | No output (no errors) |

---

_Fixed: 2026-06-04T09:55:00Z_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
