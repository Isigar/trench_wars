# 14-02 SUMMARY — Filament form-publish sets published_at (REACH-05)

**Status:** ✅ Complete

Publishing an article by flipping the Filament status Select to `published` on the edit form did NOT
set `published_at` — the form's `published_at` DateTimePicker is `->disabled()` (never submitted),
`EditArticle` has no afterSave hook, and `ArticleObserver::updated()` never set it. Only
`ArticleStatusService` (the scheduler path) set it. Result: a form-published article had
`published_at=null` and sorted unpredictably under `orderByDesc('published_at')` on `/blog`.

**Fix:** added an `ArticleObserver::saving()` hook that stamps `published_at = now()` whenever
`status === 'published'` and `published_at` is still null. Single robust place — covers the Filament
form publish, direct create-as-published, and any future path. `ArticleStatusService` sets the
timestamp explicitly, so the null-guard never overrides it; re-saving an already-published article
preserves the original timestamp. Used `setAttribute()` to mirror the array-update idiom and stay
PHPStan-clean.

- 3 tests: draft→published stamps published_at; re-save preserves the original; create-as-published stamps.

Gates: Pest (Observer 16 + Articles + ArticleStatusService, 66), PHPStan L8, Pint — all green.
