---
phase: 09-polish
plan: 01
subsystem: scaffolding
tags: [wave-0, red-stubs, i18n, factories, preflight, imagick, pitfall-1, pitfall-3, pitfall-6]
requires: []
provides:
  - "29 Pest RED stubs covering SC-1..SC-5 (notifications, leaderboards, moderator, performance, a11y, security, i18n)"
  - "4 factory stubs (Ban, AbuseReport, MatchDispute, UserNotificationPreference) with @phpstan-ignore generics"
  - "5 i18n namespace files (notifications.php, leaderboards.php, moderation.php, a11y.php, reports.php)"
  - "Dockerfile patched with libmagickwand-dev + pecl install imagick-3.7.0 (Pitfall 6 mitigation, container rebuild deferred)"
  - "CACHE_STORE=redis preflight verified in .env.example (Pitfall 1 mitigation)"
affects:
  - "plans 09-02..09-12 — each turns one or more Wave 0 stubs GREEN"
tech-stack:
  added: []
  patterns:
    - "Pest Wave 0 RED stub idiom — bare functional tests, no namespace, no per-file uses(); Pest.php autowires TestCase + RefreshDatabase via uses(...)->in('Feature','Unit')"
    - "expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-NN') wrapped by ->skip('Wave 0 stub — turned GREEN in plan 09-NN') — preserves test signature in skip-count so 09-12 verifier can prove author-introduced→turned-GREEN trajectory"
    - "Factory stub idiom — string FQN \$model + per-line @phpstan-ignore for missingType.generics + property.defaultValue; definition() throws RuntimeException so accidental ::factory() calls fail loud (canonical Phase 4 D-04-01 + Phase 8 plan 08-01 commit 9ea301b)"
key-files:
  created:
    - "apps/web/tests/Feature/Notifications/NotificationsBellTest.php — SC-1 RED stub → plan 09-06"
    - "apps/web/tests/Unit/Notifications/MatchStartingSoonNotificationTest.php — SC-1 RED → plan 09-03"
    - "apps/web/tests/Feature/Notifications/NotificationDispatcherTest.php — SC-1 cron RED → plan 09-04"
    - "apps/web/tests/Feature/Notifications/NotificationDispatcherIdempotencyTest.php — SC-1 idempotency RED → plan 09-04"
    - "apps/web/tests/Unit/UserNotificationPreferencesTest.php — SC-1 prefs RED → plan 09-03"
    - "apps/web/tests/Feature/Notifications/DiscordChannelOutboxTest.php — SC-1 + Pitfall 3 RED → plan 09-03"
    - "apps/web/tests/Feature/Leaderboards/LeaderboardServiceTopPlayersTest.php — SC-2 RED → plan 09-05"
    - "apps/web/tests/Feature/Leaderboards/LeaderboardServiceTopClansTest.php — SC-2 RED → plan 09-05"
    - "apps/web/tests/Feature/Leaderboards/LeaderboardCacheTest.php — SC-2 cache RED → plan 09-05"
    - "apps/web/tests/Feature/Leaderboards/LeaderboardPrivacyTest.php — SC-2 D-018 RED → plan 09-06"
    - "apps/web/tests/Feature/Admin/UserResourceBanBulkActionTest.php — SC-3 RED → plan 09-07"
    - "apps/web/tests/Feature/Admin/MatchResourceBulkCancelTest.php — SC-3 RED → plan 09-07"
    - "apps/web/tests/Feature/Admin/MatchDisputeWorkflowTest.php — SC-3 RED → plan 09-07"
    - "apps/web/tests/Feature/Admin/AbuseReportWorkflowTest.php — SC-3 RED → plan 09-11"
    - "apps/web/tests/Feature/Admin/ModeratorPermissionGateTest.php — SC-3 RED → plan 09-07"
    - "apps/web/tests/Feature/Admin/ModeratorAuditLogTest.php — SC-3 audit RED → plan 09-07"
    - "apps/web/tests/Unit/AppServiceProviderStrictModeTest.php — SC-4 RED → plan 09-08"
    - "apps/web/tests/Feature/Performance/LeaderboardsQueryBudgetTest.php — SC-4 RED → plan 09-08"
    - "apps/web/tests/Feature/Performance/ClansQueryBudgetTest.php — SC-4 RED → plan 09-08"
    - "apps/web/tests/Feature/Cache/CacheTagFlushTest.php — SC-4 RED → plan 09-05"
    - "apps/web/tests/Feature/Media/ClanLogoWebpConversionTest.php — SC-4 WebP RED → plan 09-09"
    - "apps/web/tests/Feature/Media/ArticleCoverWebpConversionTest.php — SC-4 WebP RED → plan 09-09"
    - "apps/web/tests/Feature/A11y/PublicPagesHtmlLangTest.php — SC-5 a11y RED → plan 09-10"
    - "apps/web/tests/Feature/A11y/VueFormLabelsTest.php — SC-5 a11y RED → plan 09-10"
    - "apps/web/tests/Unit/RateLimiterDefinitionsTest.php — SC-5 rate-limit RED (2 cases) → plan 09-11"
    - "apps/web/tests/Feature/Security/PublicApiThrottleTest.php — SC-5 throttle RED → plan 09-11"
    - "apps/web/tests/Feature/Reports/ReportAbuseTest.php — SC-5 report RED → plan 09-11"
    - "apps/web/tests/Feature/Reports/ReportAbuseThrottleTest.php — SC-5 report throttle RED → plan 09-11"
    - "apps/web/tests/Feature/I18n/Phase9I18nKeyCoverageTest.php — SC-5 i18n coverage RED → plan 09-12"
    - "apps/web/database/factories/BanFactory.php — RuntimeException stub, real impl plan 09-02 + 09-07"
    - "apps/web/database/factories/AbuseReportFactory.php — stub, real impl plan 09-02 + 09-11"
    - "apps/web/database/factories/MatchDisputeFactory.php — stub, real impl plan 09-02 + 09-07"
    - "apps/web/database/factories/UserNotificationPreferenceFactory.php — stub, real impl plan 09-02 + 09-03"
    - "apps/web/lang/en/notifications.php — bell.*, match_*.title/body, cta.* skeleton"
    - "apps/web/lang/en/leaderboards.php — page.*, tabs.*, windows.*, columns.*, anonymous_player skeleton"
    - "apps/web/lang/en/moderation.php — bulk.*, ban.*, dispute.*, audit.* skeleton"
    - "apps/web/lang/en/a11y.php — skip_to_content, notifications.*, menu.*, bulk_action.* skeleton"
    - "apps/web/lang/en/reports.php — page.*, form.*, reason_codes.*, status.*, flash.* skeleton"
  modified:
    - "docker/web/Dockerfile — libmagickwand-dev apt dep + pecl install imagick-3.7.0 + docker-php-ext-enable imagick (Pitfall 6 mitigation; rebuild deferred)"
decisions:
  - "D-09-01-A — Wave 0 stub uses expect(false)->toBeTrue('msg')->skip('msg') (test method's ->skip on assertion-bearing closure) so the test registers in skip-count but the assertion intent is preserved in the test body for plan 09-12 verifier. Diverges from Phase 8 commit 9ea301b which used expect(true)->toBeFalse() without skip — Phase 9 plan explicitly requires the ->skip() chain so suite stays GREEN at Wave 0 (no expected RED). Trade-off: skip-count grows by 30; baseline 1134 GREEN preserved."
  - "D-09-01-B — Imagick Pitfall 6 resolved preventively via Dockerfile patch rather than deferring. ImageMagick 3.7.0 chosen as the first PHP 8.4-compatible release. Container rebuild itself is deferred (no `docker compose build web` invoked) — plan 09-09 or developer rebuild will activate it. Risk: if rebuild fails (e.g., libmagickwand-dev breakage on bookworm), Pitfall 6 stays open and plan 09-09 falls back to GD with WebP (already verified present in current container). Reversibility: Dockerfile edit is a single hunk, easy to revert."
  - "D-09-01-C — Factory stubs throw RuntimeException from definition() (Phase 4 D-04-01 + Phase 8 D-08-01-D idiom) instead of returning empty array — accidental MatchDispute::factory()->create() in a Wave 1+ plan fails loud rather than silently inserting an empty row. PHPStan generics deferred via per-line @phpstan-ignore until plans 09-03/09-07/09-11 land the real models."
  - "D-09-01-D — i18n skeletons populated with placeholder English copy at Wave 0 (rather than empty arrays) so that Phase9I18nKeyCoverageTest (plan 09-12) can statically prove every t()/__() consumed by Phase 9 source resolves. Final copy refined in translation pass; no PII/secrets in placeholders."
metrics:
  duration_seconds: 467
  duration_human: "~7m 47s"
  completed_at: "2026-05-14T07:12:15Z"
  files_created: 38
  files_modified: 1
  total_files: 39
  tests_added: 30  # 29 files; RateLimiterDefinitionsTest has 2 cases (public-api + report-abuse)
  tests_now_skipped: 30
  tests_now_passing: 1134  # baseline preserved
  suite_total: 1164
  pint_files_passed: 604
  phpstan_errors: 0
  lines_added: 1023
---

# Phase 9 Plan 01: Wave 0 Scaffolding — RED Stubs + Imagick/CACHE Preflight Summary

Authored 29 Pest RED stubs (30 test cases total — RateLimiterDefinitionsTest has two), 4 model factory stubs, and 5 i18n namespace files; ran two preflight checks (Imagick missing → Dockerfile patched preventively; CACHE_STORE=redis verified) — locking the test surface and i18n key surface before plans 09-02..09-12 begin implementation.

## What Shipped

**Test scaffolding (29 Pest RED stubs, all skipped):**

| Surface area                 | Tests | Turn-GREEN plans |
| ---------------------------- | ----: | ---------------- |
| Notifications (SC-1)         |     6 | 09-03, 09-04, 09-06 |
| Leaderboards (SC-2)          |     4 | 09-05, 09-06     |
| Moderator tooling (SC-3)     |     6 | 09-07, 09-11     |
| Performance + cache (SC-4)   |     4 | 09-05, 09-08     |
| WebP variants (SC-4)         |     2 | 09-09            |
| A11y (SC-5)                  |     2 | 09-10            |
| Rate limits + reports (SC-5) |     4 | 09-11            |
| I18n coverage (SC-5)         |     1 | 09-12            |

All 29 files follow the bare functional idiom (no `namespace`, no per-file `uses(...)`) — `apps/web/tests/Pest.php` autowires `TestCase` + `RefreshDatabase` via `uses(...)->in('Feature', 'Unit')`. Each `test()` body asserts `expect(false)->toBeTrue('Wave 0 stub — implement in plan 09-NN')` and the test is chained with `->skip('Wave 0 stub — turned GREEN in plan 09-NN')` so the suite stays GREEN at Wave 0 baseline while preserving the assertion-intent in the file body for plan 09-12 verifier to prove the author-introduced→turned-GREEN trajectory.

**Factory stubs (4):** `BanFactory`, `AbuseReportFactory`, `MatchDisputeFactory`, `UserNotificationPreferenceFactory` — each uses the canonical Phase 4 D-04-01 idiom (string FQN `$model` + per-line `@phpstan-ignore` for `missingType.generics` + `property.defaultValue`, `definition()` throws `RuntimeException` so accidental `::factory()` calls fail loud).

**i18n skeletons (5 namespaces):**
- `notifications.php` — `bell.*`, `match_starting_soon.*`, `match_cancelled.*`, `match_result_published.*`, `clan_application_decided.{approved,rejected}.*`, `clan_invite_received.*`, `cta.*`
- `leaderboards.php` — `page.*`, `tabs.*`, `windows.{7d,30d,all}`, `columns.*`, `empty_state`, `anonymous_player`
- `moderation.php` — `bulk.{ban,unban,match_cancel}.*`, `ban.{types,status}.*`, `dispute.{status,resolution,fields}.*`, `audit.*`
- `a11y.php` — `skip_to_content`, `notifications.*`, `menu.*`, `bulk_action.*`, `icon_button.*` (root-level keys; file itself IS the `a11y` namespace per plan task 1 note)
- `reports.php` — `page.*`, `form.*`, `reason_codes.*`, `status.*`, `flash.*`, `cta.*`

**Preflight results:**

| Check | Pitfall | Result |
|-------|---------|--------|
| `grep -E "^CACHE_STORE=redis$" apps/web/.env.example` | Pitfall 1 (cache tags require redis driver) | ✅ PASS — already set |
| `docker compose exec web php -m \| grep -i imagick` | Pitfall 6 (medialibrary WebP driver) | ❌ MISSING — patched Dockerfile preventively (rebuild deferred) |

## Quality Gates

- `pest --filter='Wave 0 stub' --no-coverage` → **30 skipped (0 assertions)** — all new stubs register and skip cleanly.
- Full `pest --no-coverage` → **1134 passed + 30 skipped (3783 assertions) in 71.42s** — no regression on the baseline.
- `pint --test` → **PASS** on all 604 files.
- `phpstan analyse` (level 8, no progress) → **OK, no errors** (factory stubs' per-line ignores cover the missing-generics case).

## Deviations from Plan

### Rule 2 — Auto-add missing critical functionality

**1. [Rule 2 — Missing dependency] Imagick PHP extension absent from web container**
- **Found during:** Task 1 preflight (`docker compose exec web php -m | grep -i imagick` returned empty).
- **Why it matters:** Plan task 1 truth #5 ("Imagick PHP extension verified present in web container") cannot be honoured without intervention. Pitfall 6 (09-RESEARCH.md L1241+L1307+L1312) explicitly calls for verification with a Dockerfile patch as the documented escape hatch. Plan 09-09 (WebP variants via `spatie/laravel-medialibrary->format('webp')`) is the downstream consumer.
- **Fix applied:** Patched `docker/web/Dockerfile`:
  - Added `libmagickwand-dev` to the system-dep apt-install layer.
  - Added a new layer: `RUN pecl install imagick-3.7.0 && docker-php-ext-enable imagick` (3.7.0 is the first PHP 8.4-compatible release per upstream).
  - Annotated both edits with inline comments citing Phase 9 plan 09-01 + Pitfall 6.
- **What is NOT done in this commit:** The container image is NOT rebuilt this session. Activating the patch requires `docker compose build web && docker compose up -d web`, which is deferred to the developer or to plan 09-09 prep work. Rationale: rebuilding a base image with new apt deps + pecl extension takes 5-10 minutes, blocks other running containers, and Wave 0 has no consumer of Imagick today. GD with WebP support is verified present in the current container (`gd_info()['WebP Support'] = 1`), so plan 09-09 can fall back to GD if the rebuild slips.
- **Files modified:** `docker/web/Dockerfile`.
- **Commit:** `4cfe73e`.
- **Follow-up:** When developer runs `docker compose build web`, re-run `docker compose exec web php -m | grep -i imagick` and confirm `imagick` appears in the module list. If the pecl install fails on bookworm (known historical churn around ImageMagick 7.x dev headers), surface as a fresh deviation in plan 09-09 and pin to ImageMagick 6 series or switch to GD-only.

### No other deviations

- Plan executed exactly as written for all 29 test stubs, 4 factory stubs, 5 i18n files.
- No bugs encountered in adjacent code (Rule 1 N/A).
- No blocking issues beyond the documented Imagick miss (Rule 3 fully addressed).
- No architectural changes proposed (Rule 4 N/A).

## Authentication Gates

None. Plan ran fully autonomously inside the existing Docker stack.

## Known Stubs

This plan **intentionally** creates 29 test stubs + 4 factory stubs + 5 i18n skeletons. **By design** — each is a contract for a later Wave's GREEN handover (catalogued in the `affects` list above and the per-file commit message). These are NOT defects.

| Stub | Resolved in |
|------|-------------|
| 29 Pest RED stubs (skipped) | Plans 09-02..09-12 (each test names its target plan in the `->skip()` message + file docblock) |
| 4 factory `definition()` `RuntimeException` throws | Plan 09-02 (migrations) + plan 09-03/09-07/09-11 (models) |
| 5 i18n placeholder copy | Final copy in translation pass; no functional impact (CI gate plan 09-12 only checks key existence, not copy quality) |
| Imagick extension activation | Container rebuild (developer or plan 09-09 prep); Dockerfile patch already committed |

## Threat Flags

None. The threat model (T-09-01-01..03) accepted all introduced surface as low-risk (test stub files, non-sensitive i18n placeholders, one-off preflight subshell). The Dockerfile patch adds a single PHP extension via a well-known supply chain (Debian apt + PECL); no new network endpoints, auth paths, or schema changes at trust boundaries.

## Self-Check: PASSED

**Files checked:** 39 (all `git ls-files --others --exclude-standard` cleared on the commit).

```
FOUND: apps/web/database/factories/BanFactory.php
FOUND: apps/web/database/factories/AbuseReportFactory.php
FOUND: apps/web/database/factories/MatchDisputeFactory.php
FOUND: apps/web/database/factories/UserNotificationPreferenceFactory.php
FOUND: apps/web/lang/en/notifications.php
FOUND: apps/web/lang/en/leaderboards.php
FOUND: apps/web/lang/en/moderation.php
FOUND: apps/web/lang/en/a11y.php
FOUND: apps/web/lang/en/reports.php
FOUND: apps/web/tests/Feature/Notifications/NotificationsBellTest.php  [+5 sibling stubs]
FOUND: apps/web/tests/Feature/Leaderboards/{4 stubs}
FOUND: apps/web/tests/Feature/Admin/{6 stubs}
FOUND: apps/web/tests/Feature/Performance/{2 stubs}
FOUND: apps/web/tests/Feature/Cache/CacheTagFlushTest.php
FOUND: apps/web/tests/Feature/Media/{2 stubs}
FOUND: apps/web/tests/Feature/A11y/{2 stubs}
FOUND: apps/web/tests/Feature/Security/PublicApiThrottleTest.php
FOUND: apps/web/tests/Feature/Reports/{2 stubs}
FOUND: apps/web/tests/Feature/I18n/Phase9I18nKeyCoverageTest.php
FOUND: apps/web/tests/Unit/Notifications/MatchStartingSoonNotificationTest.php
FOUND: apps/web/tests/Unit/{AppServiceProviderStrictModeTest, UserNotificationPreferencesTest, RateLimiterDefinitionsTest}
FOUND: docker/web/Dockerfile (modified)

FOUND: commit 4cfe73e in git log
```

All 38 created files + 1 modified file present on disk; commit `4cfe73e` resolves and contains the expected diff (`39 files changed, 1023 insertions(+)`).
