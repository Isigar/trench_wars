# 13-03 SUMMARY — Public Players index page (REACH-03)

**Status:** ✅ Complete

`/players` was linked from the header nav AND emitted into `sitemap.xml`, but no `GET /players` route
existed — a guest clicking Players or a crawler following the sitemap hit a 404. Built the public index.

- `PlayerSummaryData` DTO — listing-card projection (slug/displayName/avatarUrl/countryCode); TS types regenerated.
- `PlayersIndexController` — mirrors `ClanDirectoryController`: optional `?q=` name search + pagination,
  with D-018 privacy filtering via `PlayerPrivacyGate::canShowInSearch` (same gate the search surface
  uses; private/community/clan tiers hidden from viewers who shouldn't see them, own-profile passes).
- Route `GET /players` (`players.index`) declared before `/players/{slug}` so the literal path binds.
- `Players/Index.vue` — search box + responsive player-card grid + Prev/Next pagination.
- Removed the stale "TODO Phase 9: wire /players index" comment in `PublicLayout`.
- `players.index.*` i18n keys.
- 4 feature tests: page resolves (no 404), private hidden from anonymous, private visible to self, name search.

Gates: Pest (4 new + Sitemap 14 + A11y), PHPStan L8, Pint, vue-tsc, NoHardcodedStrings — all green.

## Phase 13 close
All 3 MEDIUM plans complete (13-01 withdraw UI, 13-02 double-elim fix, 13-03 players index). REACH-01/02/03 Met.
