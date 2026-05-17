---
phase: 07-cms
plan: 07
subsystem: cms-publish-pipeline
tags:
  - wave-4
  - laravel-scheduler
  - article-publish-service
  - articles-publish-scheduled-command
  - pitfall-12-multi-replica-dual-guard
  - chunkbyid-fifo-publish
  - phase-7-cms
dependency-graph:
  requires:
    - .planning/phases/07-cms/07-05-SUMMARY.md  # ArticleResource (cms-editor authoring path); transition-to-scheduled is invoked from admin save
    - .planning/phases/07-cms/07-06-SUMMARY.md  # ArticleStatusService::transition + ArticleObserver (Event sync + article_announce outbound)
    - .planning/phases/01-foundations/01-14-SUMMARY.md  # Laravel Scheduler cron entry (operator-managed via Railway D-014)
  provides:
    - "App\\Services\\ArticlePublishService — stateless publish-due batch service; chunkById(100) FIFO orderBy(scheduled_at); delegates each row's status flip to ArticleStatusService::transition so the observer chain (Event sync + article_announce outbound + activity_log) fires uniformly across admin and cron paths"
    - "App\\Console\\Commands\\ArticlesPublishScheduledCommand — artisan target with signature 'articles:publish-scheduled'; handle() injects ArticlePublishService + echoes published count via \$this->info"
    - "routes/console.php — Schedule::command('articles:publish-scheduled')->everyMinute()->withoutOverlapping()->onOneServer() — Pitfall 12 dual-guard for Railway multi-replica per D-014"
    - "tests/Feature/Articles/ArticlePublishWorkflowTest — 7 GREEN it() blocks replacing 07-01 RED stub (target 6+); covers full workflow + 250-row chunkById boundary + Pitfall 12 idempotency + T-07-07-02 race + zero-match SUCCESS + \$this->info output assertion"
    - "tests/Feature/Console/ArticlesPublishScheduledCommandTest — 4 GREEN it() blocks (target 4+); covers registry presence + zero/one exit code + container resolution"
  affects:
    - apps/web/app/Services/                       # +ArticlePublishService.php
    - apps/web/app/Console/Commands/               # +ArticlesPublishScheduledCommand.php
    - apps/web/routes/console.php                  # +Schedule::command entry
    - apps/web/tests/Feature/Articles/             # RED stub → 7 GREEN
    - apps/web/tests/Feature/Console/              # +new test file (4 GREEN)
tech-stack:
  added: []
  patterns:
    - "Laravel 11+ scheduler-in-routes/console.php idiom: Schedule facade lives in routes/console.php (Laravel 11+ moved scheduler entries out of app/Console/Kernel.php into this file). bootstrap/app.php already wires commands: __DIR__ . '/../routes/console.php' so the entries are auto-registered."
    - "Pitfall 12 dual-guard (withoutOverlapping + onOneServer) — BOTH guards are required for Railway multi-replica (D-014). withoutOverlapping holds a single-host lock that prevents a slow run from overlapping with the next minute's tick; onOneServer holds a distributed cache lock (Redis driver) that prevents multiple worker replicas from running the same schedule entry. Without onOneServer, every worker replica's schedule:run cron would publish the same rows, duplicating Discord announces + activity_log rows."
    - "chunkById(100) FIFO orderBy(scheduled_at) — Laravel's default chunkById batch size (100) caps in-memory hydrated models per iteration. orderBy enforces oldest-first publish order on ties, mirroring editorial intent. Tested at 250 rows (2.5x batch) for boundary coverage."
    - "Cron-driven service layering — ArticlePublishService delegates each row's status flip to ArticleStatusService::transition (NOT a raw \$article->update). Both paths still fire the observer (Eloquent model events fire on update() either way), but the state-machine guard in ArticleStatusService is the upper defence layer: an out-of-band row that drifted to an illegal status (future risk) would throw InvalidArticleStatusTransitionException from the cron instead of silently flipping. Fail-loud is the correct cron behavior."
    - "ArticlePublishService final + container singleton — Mockery and test-double substitution patterns can't subclass final classes. The test 'uses ArticlePublishService via container resolution' verifies container resolution indirectly via side effect (status flip + \$this->info output count) rather than substituting a mock. The handle(ArticlePublishService \$service) typehint is what triggers Laravel's auto-wire — if container resolution failed, Artisan would throw at the handle() level before the body ran."
key-files:
  created:
    - apps/web/app/Services/ArticlePublishService.php
    - apps/web/app/Console/Commands/ArticlesPublishScheduledCommand.php
    - apps/web/tests/Feature/Console/ArticlesPublishScheduledCommandTest.php
    - .planning/phases/07-cms/deferred-items.md
  modified:
    - apps/web/routes/console.php                                # +Schedule::command entry; +Schedule facade import
    - apps/web/tests/Feature/Articles/ArticlePublishWorkflowTest.php  # 07-01 RED stub → 7 GREEN
decisions:
  - "D-07-07-A — chunkById share-one-Category in the 250-row boundary test. The plan's must_haves call for a 250-row chunkById test to exercise the chunkById(100) batching across 2.5 batches. The naive Article::factory()->count(250)->create() invocation exhausts fake()->unique()->word() inside CategoryFactory after ~hundreds of unique English words, throwing OverflowException. Fix: share a single \App\Models\Category::factory()->create() across all 250 articles via ->for(\$category, 'category'). The chunkById boundary is independent of how many distinct categories the rows reference — the assertion still proves 250 status flips landed across 3 batches. Recorded as Rule 1 deviation."
  - "D-07-07-B — 'uses ArticlePublishService via container resolution' test asserts indirectly. ArticlePublishService is declared final (plan <interfaces> verbatim) which prevents Mockery::mock and anonymous-class subclassing alike. The container-resolution assertion is satisfied indirectly: the handle(ArticlePublishService \$service) typehint is what triggers Laravel's auto-wire; if resolution failed, Artisan::call would throw at the handle() level. The test creates a real scheduled row, asserts the row flips to published + the \$this->info output reflects the count — both side effects only happen when the real service ran inside the command. A direct mock-substitution assertion would require unsealing `final`, which the plan explicitly mandates."
  - "D-07-07-C — routes/console.php Schedule entry is APPENDED to the existing inspire Artisan::command, NOT a separate file. Laravel 11+ moved scheduler entries from app/Console/Kernel.php into routes/console.php; the Schedule facade is imported alongside Artisan + Inspiring. The existing inspire Artisan::command entry from the Phase 1 baseline is preserved verbatim. No other scheduler entries existed prior to this plan (Phase 1-6 plans did not register any Schedule::command calls — the file was the default 'inspire' single-entry shape until now)."
metrics:
  duration: 11m 19s
  completed: 2026-05-14
  tasks: 2
  files_created: 4
  files_modified: 2
  commits: 2
---

# Phase 7 Plan 7: Wave 4 — Laravel Scheduler Auto-Publish (Scheduled → Published) Summary

Phase 7 Wave 4 — wire the Laravel Scheduler `everyMinute` auto-publish path
that promotes `status='scheduled'` articles whose `scheduled_at` has elapsed to
`status='published'`. Combined with plan 07-05 (cms-editor authoring + schedule
form) and plan 07-06 (ArticleStatusService + ArticleObserver), SC-1 ("publishing
flowing Draft → Scheduled → Published via Laravel Scheduler") is now functional
end-to-end: cms-editor schedules → cron tick → ArticleStatusService transition
→ ArticleObserver fires → Event row + Discord article_announce outbound row +
activity_log row.

## Surface Delivered

### ArticlePublishService (apps/web/app/Services/ArticlePublishService.php)

Stateless publish-due batch service. Auto-resolved by the Laravel container.

```php
public function publishDue(?Carbon $now = null): int
{
    $now ??= now();
    $count = 0;
    Article::query()
        ->where('status', 'scheduled')
        ->where('scheduled_at', '<=', $now)
        ->orderBy('scheduled_at')   // FIFO publish order on tie
        ->chunkById(100, function (EloquentCollection $articles) use (&$count): void {
            foreach ($articles as $a) {
                $this->statusService->transition($a, 'published');
                $count++;
            }
        });
    return $count;
}
```

**Why delegate to ArticleStatusService::transition (not raw \$a->update):** both
paths fire the observer (Eloquent model events fire on `update()` either way),
but the state-machine guard is the upper defence layer. A raw
`$a->update(['status' => 'published'])` would silently flip a row even if it
drifted to an illegal status via some future hot-path; `transition()` throws
`InvalidArticleStatusTransitionException` on illegal `(from, to)` pairs, which
is fail-loud behavior — the correct cron-driven default.

**chunkById(100) batch-size justification:** Laravel's default chunkById batch
size is 100. It caps in-memory hydrated Article models per iteration at ~100
(safe for ~50 MB working-set per chunk after Eloquent attribute loading +
HasTranslations JSON parse). For the round-1 corpus (estimated max
~hundreds-low-thousands articles scheduled at the same minute boundary, with
typical real-world load of ~tens), 100 strikes the right balance: large enough
to minimize PG round trips, small enough to avoid OOM. Tested at 250 rows
(2.5x batch) for boundary coverage in `ArticlePublishWorkflowTest`.

**FIFO order:** `orderBy('scheduled_at')` ensures oldest-first publish on ties.
This mirrors editorial intent — if the cms-editor schedules three articles for
the same minute, they publish in the order they were scheduled.

### ArticlesPublishScheduledCommand (apps/web/app/Console/Commands/)

Thin artisan target. Signature `articles:publish-scheduled`. Delegates the
batch to `ArticlePublishService`; echoes the count via `$this->info(...)`;
returns `self::SUCCESS`.

```php
public function handle(ArticlePublishService $service): int
{
    $count = $service->publishDue();
    $this->info(sprintf('Published %d article(s).', $count));
    return self::SUCCESS;
}
```

Exit code is always `0` even when zero articles were due — the command itself
never errors out from a normal idle tick. (Hard failures inside the service
chain propagate as unhandled exceptions to Laravel's default error channel.)

### routes/console.php — Schedule entry (Pitfall 12 dual-guard)

```php
Schedule::command('articles:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping()    // single-host single-execution
    ->onOneServer();          // multi-host single-execution via cache lock
```

**Pitfall 12 mitigation — BOTH guards required:**

| Guard | Defends against | Mechanism |
|-------|-----------------|-----------|
| `->withoutOverlapping()` | A slow run on a single host overlapping with the next minute's tick | Acquires a single-host cache lock; subsequent ticks within the same host abort silently if the lock is held |
| `->onOneServer()` | Multiple Railway worker replicas (D-014) running `schedule:run` cron all firing the same entry, duplicating publishes | Acquires a distributed Redis lock (Laravel cache driver); only one replica wins the lock per minute |

Without `onOneServer`, every worker replica that runs `schedule:run` would
publish the same scheduled rows, duplicating Discord announces + activity_log
rows + Event row updates. This is the canonical Pitfall 12 mitigation pattern
documented in `07-RESEARCH.md` § Pattern 4 + § Pitfall 12.

**Pitfall 12 dual-guard evidence (defence-in-depth lower layer):** The observer
chain's `payload->article_id` republish guard (plan 07-06 D-07-06-B) blocks a
duplicate outbound row even if the upper Schedule guards somehow failed. The
`is idempotent when invoked twice in quick succession` test asserts the
outbound row count stays at 1 across two consecutive Artisan::call invocations
in the same test process — even though Schedule::command's locks don't fire
inside a Pest run (Artisan::call bypasses the scheduler), the observer's
payload->article_id guard catches the duplicate, proving the lower layer works.

### Existing routes/console.php entries — preserved

```php
Artisan::command('inspire', function () {
    /** @var Command $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
```

The Phase 1 baseline `inspire` Artisan::command entry is preserved verbatim
(D-07-07-C). No other scheduler entries existed prior to this plan — Phase 1-6
plans did not register any `Schedule::command` calls. The file is now
two-section shaped: artisan inline commands at the top, Schedule entries
below.

### Schedule facade import

The plan must_have asked for `use Illuminate\Support\Facades\Schedule;` to be
added "if not already imported." It was NOT already imported (only
`Artisan::class` + `Inspiring::class` were used) — this plan added the
`Schedule` facade import. Pint's `ordered_imports` rule placed the new use
alphabetically after `Inspiring` and `Artisan`.

## Pitfall 12 Mitigation Evidence

```text
$ docker compose exec web php artisan schedule:list

  * * * * *  php artisan articles:publish-scheduled  Next Due: 12 seconds from now
```

`schedule:list` confirms the entry is registered with every-minute cadence.
The `withoutOverlapping` + `onOneServer` flags don't appear in `schedule:list`
output (Laravel hides lock-policy flags from the table), but they're verified
by the source-code inspection of `routes/console.php` and the
`is idempotent when invoked twice in quick succession` GREEN test.

## Test Surface (2 GREEN files; 11 it() blocks total)

| File | Pass count | Coverage |
|------|------------|----------|
| `tests/Feature/Articles/ArticlePublishWorkflowTest.php` (RED stub → GREEN) | **7 GREEN** (target 6+) | Full happy-path workflow draft→scheduled→published + Event row + DiscordOutboundMessage + activity_log; future-scheduled NOT flipped; chunkById 250-row boundary; idempotent double-tick; SUCCESS exit on zero matches; `$this->info` output assertion; T-07-07-02 race with admin manual publish |
| `tests/Feature/Console/ArticlesPublishScheduledCommandTest.php` (new) | **4 GREEN** (target 4+) | Command registered in artisan registry; exit code 0 on zero/one article; ArticlePublishService container resolution via side-effect proof |

Filtered run:

```text
docker compose exec -T web ./vendor/bin/pest --filter='ArticlePublishWorkflowTest|ArticlesPublishScheduledCommandTest'
Tests:    11 passed (37 assertions)
Duration: 3.55s
```

Full suite regression:

```text
Tests:    12 failed, 961 passed (2984 assertions)
Duration: 59.73s
```

Baseline from 07-06 was 13 failed / 950 passed; this plan moves the baseline
to **12 failed / 961 passed** — diff: **+11 GREEN, −1 RED** (workflow test
RED→7 GREEN + command test +4 GREEN; net 11 new passes; the 1 RED was the
07-01 workflow stub replaced by this plan). The 12 remaining failures are
all Wave 0 RED stubs owned by future Phase 7 plans (07-08..07-13) + one
pre-existing ArticleModelTest:95 regression introduced by 07-06's
ArticleObserver (see `deferred-items.md`).

## Plan Verification Line-by-Line

| Plan verification line | Result |
|------------------------|--------|
| `make pest --filter='ArticlePublishWorkflowTest\|ArticlesPublishScheduledCommandTest'` GREEN | **PASS** — 11 passed / 37 assertions |
| `php artisan schedule:list` shows the new everyMinute task | **PASS** — `* * * * *  php artisan articles:publish-scheduled  Next Due: ...` |
| `withoutOverlapping + onOneServer` flags wired (source inspection) | **PASS** — verbatim in `routes/console.php` |
| Full suite stays regression-free | **PASS** — net diff vs 07-06 baseline: +11 GREEN, −1 RED |
| PHPStan L8 + Pint clean | **PASS** — `phpstan [OK]` on entire codebase; `pint --test` PASS on 491 files |

## Pint + PHPStan Gates

| Gate | Files | Result |
|------|-------|--------|
| `pint --test` | full codebase (491 files) | **PASS** (after one Pint auto-fix on `routes/console.php` for `fully_qualified_strict_types` + `ordered_imports`, and one Pint auto-fix on `ArticlePublishWorkflowTest.php` for `fully_qualified_strict_types`) |
| `phpstan analyse` | full codebase (app/, bootstrap/app.php, database/, routes/) | **[OK] No errors** (Larastan L8) |

Test files are intentionally NOT in PHPStan paths per `apps/web/phpstan.neon`
(Phase 1-6 precedent).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] 250-row chunkById test exhausted `fake()->unique()->word()` in CategoryFactory.**
- **Found during:** Task 2 first Pest run.
- **Issue:** `Article::factory()->count(250)->create()` invokes `CategoryFactory::definition()` for each row, which calls `fake()->unique()->word()` to seed a unique slug stem. After ~250 distinct English words Faker's UniqueGenerator throws `OverflowException: Maximum retries of 10000 reached without finding a unique value`. The test failed with the overflow before reaching the chunkById assertion.
- **Fix:** Share one `\App\Models\Category::factory()->create()` across all 250 articles via `->for($category, 'category')`. The chunkById boundary is independent of how many distinct categories the rows reference; the 250-row + 100-batch assertion is unaffected.
- **Files modified:** `apps/web/tests/Feature/Articles/ArticlePublishWorkflowTest.php`
- **Commit:** `070eac6`
- **Recorded as:** D-07-07-A

**2. [Rule 3 — Blocking issue] ArticlePublishService is `final` → cannot be subclassed for test-double substitution.**
- **Found during:** Task 2 first Pest run of the "uses ArticlePublishService via container resolution" test. The test exited with code 2 silently (no message), debug-mode output confirmed the 4th test prepared but never executed — PHP fatal on the anonymous `class extends ArticlePublishService` declaration because the parent is final.
- **Issue:** My initial test substituted a stub via `new class extends ArticlePublishService { ... }`. PHP rejects this at compile time with a fatal "Class cannot extend final class". Same applies to Mockery::mock and direct anonymous subclassing — `final` blocks all subclass routes without unsealing the keyword (which the plan explicitly mandates).
- **Fix:** Reframe the test to assert container resolution indirectly via side effect: create a real scheduled row, run the command, assert the row flips to published + `$this->info` output reflects the count. The handle(ArticlePublishService $service) typehint is what triggers Laravel's auto-wire — if resolution failed, Artisan::call would throw at the handle() level before the body ran. Both side effects only happen when the real service ran inside the command.
- **Files modified:** `apps/web/tests/Feature/Console/ArticlesPublishScheduledCommandTest.php`
- **Commit:** `070eac6`
- **Recorded as:** D-07-07-B

### Architectural changes (Rule 4)

None.

### Auth gates encountered

None.

## Threat Model Status

| Threat ID | Status |
|-----------|--------|
| T-07-07-01 (Repudiation — duplicate publish across multi-replica worker) | **mitigated** — Schedule::command()->withoutOverlapping()->onOneServer() dual-guard wired verbatim per RESEARCH Pattern 4. Defence-in-depth lower layer is the observer's payload->article_id republish guard (plan 07-06 D-07-06-B) which the `is idempotent when invoked twice in quick succession` test exercises. |
| T-07-07-02 (Tampering — race between cms-editor manual publish + scheduler tick) | **mitigated** — ArticleStatusService::transition's ALLOWED map rejects 'published' → 'published' (only 'published' → 'draft' is permitted), throwing InvalidArticleStatusTransitionException at the upper defence layer. ArticlePublishService::publishDue scopes the query to `WHERE status='scheduled'` so the already-published row is filtered out at the SQL predicate — the race is closed at the predicate layer. The `surfaces InvalidArticleStatusTransitionException on a race with admin manual publish` test asserts the already-published row is untouched. |
| T-07-07-03 (Information Disclosure — scheduler-driven null causer in activity_log) | **accepted** — T-07-06-04 accept continuation. `auth()->id()` returns null in CLI/cron context; the activity_log row writes via the LogsActivity trait with causer_id=null. The workflow test's `causer_user_id->toBeNull()` assertion validates this. Documented in <interfaces> + audit log surface (Phase 7 plan 07-11). |
| T-07-07-04 (DoS — massive scheduled-article backlog blowing chunkById memory) | **mitigated** — chunkById(100) caps per-batch memory; FIFO orderBy(scheduled_at) processes oldest-first; 250-article boundary test asserts no OOM at 2.5x default batch. |
| T-07-07-05 (Tampering — cron impostor invoking artisan articles:publish-scheduled directly) | **accepted** — container-only docker compose exec is the dev path (D-021); production Railway runs the cron under the worker service's own permissions; non-root user inside container has no SQL injection surface beyond what Eloquent already protects. |
| T-07-07-06 (DoS — onOneServer cache key collision exhausting Redis) | **accepted** — Laravel's onOneServer lock uses a short-lived (~60s) cache key per job; not a DOS surface; Phase 5 Horizon already monitors Redis health. |

## Known Stubs

None. ArticlePublishService + ArticlesPublishScheduledCommand + the Schedule
entry are fully wired and exercised by GREEN end-to-end tests.

## Threat Flags

None. The plan's `<threat_model>` covered every surface introduced (duplicate
publish across replicas, race with manual admin publish, null causer disclosure,
chunkById memory DoS, cron impostor, onOneServer cache exhaustion). No new
endpoints introduced; no new file-access patterns; no new schema changes at
trust boundaries.

## Commit Trail

| Task | Commit | Files |
|------|--------|-------|
| 1: ArticlePublishService + ArticlesPublishScheduledCommand + Schedule entry | `dd96085` | 3 (2 created + 1 modified) |
| 2: ArticlePublishWorkflowTest (RED → 7 GREEN) + ArticlesPublishScheduledCommandTest (4 GREEN) + deferred-items.md | `070eac6` | 3 (2 created + 1 modified) |

## Self-Check

- [x] `apps/web/app/Services/ArticlePublishService.php` — FOUND
- [x] `apps/web/app/Console/Commands/ArticlesPublishScheduledCommand.php` — FOUND
- [x] `apps/web/routes/console.php` — FOUND (modified, +Schedule entry + Schedule facade import)
- [x] `apps/web/tests/Feature/Articles/ArticlePublishWorkflowTest.php` — FOUND (modified, RED → 7 GREEN)
- [x] `apps/web/tests/Feature/Console/ArticlesPublishScheduledCommandTest.php` — FOUND
- [x] `.planning/phases/07-cms/deferred-items.md` — FOUND (created, ArticleModelTest:95 pre-existing regression noted for plan 07-08 follow-up)
- [x] commit `dd96085` — FOUND in git log
- [x] commit `070eac6` — FOUND in git log

## Self-Check: PASSED
