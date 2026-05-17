## Discovered during plan 07-07

### ArticleModelTest:95 — pre-existing UniqueConstraintViolationException

**File:** `apps/web/tests/Feature/Models/ArticleModelTest.php` line 87 ("it exposes events() MorphMany pointing at the polymorphic events table")

**Issue:** Test manually creates an Event row for a new Article, but the
ArticleObserver introduced in plan 07-06 already auto-creates the Event row on
`Article::factory()->create()` via `syncEvent()`. The manual `Event::create([
'eventable_type' => Article::class, 'eventable_id' => $article->id, ... ])`
then violates the `events_one_per_owner` UNIQUE (eventable_type, eventable_id)
partial index.

**Discovered when:** Running full Pest suite for plan 07-07 regression check.
This failure was already in the 07-06 baseline (13 failed / 950 passed); plan
07-06 SUMMARY did not enumerate it explicitly.

**Out of scope for 07-07** — plan 07-07 only adds the auto-publish scheduler
path. The fix is a one-line removal of the manual `Event::create(...)` block in
ArticleModelTest:95-101 (the auto-created row already exists; the assertion
`->and($article->fresh()?->events->count())->toBe(1)` would pass against the
observer-created row).

**Suggested fix path:** Patch ArticleModelTest in plan 07-08 or as a follow-up
chore commit — convert the test to assert the auto-created Event row exists
rather than inserting a second one.
