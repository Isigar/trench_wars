# 14-03 + 14-04 SUMMARY — MatchPlayerStat + MatchEvent admin surfaces (REACH-06, REACH-07)

**Status:** ✅ Complete (combined — both are relation managers on MatchResource sharing the GameMatch
relations + admin lang, so they ship in one commit).

## 14-03 — MatchPlayerStat admin correction (REACH-06)
`MatchPlayerStat` documented an "admin manually corrects a stat" flow (D-012, LogsActivity for
non-repudiation) but had NO Filament surface — a wrong CRCON-aggregated kill/death/score count could
not be fixed. Added `PlayerStatsRelationManager` (edit-only: no create/delete — rows are produced by
the aggregator, composite-unique per match+player). Editing routes through Eloquent update → the
`MatchPlayerStatObserver` audit fires.

## 14-04 — MatchEvent read-only view (REACH-07)
`MatchEvent` (the append-only normalised CRCON event stream feeding stat aggregation + result
inference) was exposed nowhere — an admin curating a low-confidence (`manual_entry_required`) result
couldn't inspect the underlying timeline. Added `EventsRelationManager` (strictly read-only: events are
immutable; no create/edit/delete) with an event_type filter.

## Shared
- `GameMatch::playerStats()` + `GameMatch::matchEvents()` HasMany relations.
- Registered both managers in `MatchResource::getRelations()`.
- `admin.match_player_stats.*` + `admin.match_events.*` i18n blocks.
- 3 Filament Livewire tests: both registered, stat-edit persists + audits, events list is read-only +
  match-scoped.

Gates: Pest (Phase8 + Admin + I18n, 311), PHPStan L8, Pint — all green.

## Phase 14 close
All 4 LOW plans complete (14-01 ban enforcement, 14-02 published_at, 14-03 stat-correction, 14-04
event-view). REACH-04/05/06/07 Met.
