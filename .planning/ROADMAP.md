# Roadmap: Trenchwars

## Milestones

- ✅ **v1.0 Round-1** — Phases 1-9 (shipped 2026-05-17 — see [milestones/v1.0-ROADMAP.md](milestones/v1.0-ROADMAP.md))
- ✅ **v1.1 Completion** — Phases 10-12 (shipped 2026-06-04 — see [milestones/v1.1-ROADMAP.md](milestones/v1.1-ROADMAP.md))
- 🚧 **v1.2 Reachability completion** — Phases 13-14 (in progress; closes the 2026-06-06 feature-reachability audit gaps)

## Phases

### Phase 13: Reachability — MEDIUM gaps

**Goal**: The remaining MEDIUM-severity reachability gaps are closed — applicants can withdraw their own clan application from the UI, the double-elimination losers bracket no longer overwrites a participant for N≥8, and the public Players index page that the nav and sitemap link to actually exists.
**Depends on**: Phase 2 (ClanApplication), Phase 6 (BracketAdvancementService), Phase 9 (PlayersJsonController, PublicLayout nav)
**Requirements**: REACH-01, REACH-02, REACH-03

Plans:

- [x] 13-01 — Applicant withdraw-application UI on /my-clan (REACH-01)
- [x] 13-02 — Fix double-elim N≥8 losers-bracket slot collision in BracketAdvancementService (REACH-02)
- [x] 13-03 — Public Players index page (route + controller + Vue), remove stale nav TODO (REACH-03)

**Details:**
`ClanApplicationService::cancel` + `applications.cancel` route already work but had no UI; surface the applicant's own pending application with a Withdraw button (mirrors the v1.2 received-invites surface). `BracketAdvancementService::resolveSlot` derives the destination slot from the SOURCE bracket's position parity only, so for N≥8 an LB-internal winner and a dropped WB loser both target the same slot and one real participant is overwritten — fixed with a focused N=8 repro test. `/players` is linked from `PublicLayout` nav and emitted into `sitemap.xml` but no route exists (404); build the public index consuming the existing `PublicPlayerData` projection.

### Phase 14: Reachability — LOW gaps

**Goal**: The remaining LOW-severity reachability gaps are closed — issued bans are enforced at the authentication layer (not just recorded), articles published via the Filament form get a published_at timestamp, and admins have UI surfaces to correct per-player match stats and inspect the normalised RCON event timeline.
**Depends on**: Phase 1 (auth/BanService), Phase 7 (ArticleStatusService), Phase 8 (MatchPlayerStat, MatchEvent)
**Requirements**: REACH-04, REACH-05, REACH-06, REACH-07

Plans:

- [ ] 14-01 — Ban enforcement middleware blocks banned users from authenticated access; remove dead docblock (REACH-04)
- [ ] 14-02 — Filament article form-publish sets published_at via ArticleStatusService (REACH-05)
- [ ] 14-03 — MatchPlayerStat admin correction relation manager on MatchResource (REACH-06)
- [ ] 14-04 — MatchEvent read-only relation manager on MatchResource (REACH-07)

**Details:**
`BanService::isCurrentlyBanned` + `User::activeBan` exist but nothing calls them in the request lifecycle, and a dead docblock references a never-built "ban-check middleware (plan 09-11)" — add the middleware so a banned user is denied authenticated access. Publishing an article by flipping the Filament status Select to `published` does not set `published_at` (only the scheduler path does), so it sorts unpredictably on `/blog` — route the form publish through `ArticleStatusService`. `MatchPlayerStat` documents an "admin manually corrects a stat" flow with no Filament surface; `MatchEvent` (the RCON event stream feeding stat aggregation + result inference) has no admin view — add the two relation managers on `MatchResource`.

<details>
<summary>✅ v1.0 Round-1 (Phases 1-9) — SHIPPED 2026-05-17</summary>

- [x] Phase 1: Foundations (18/18 plans) — completed 2026-05-04
- [x] Phase 2: Clans & tags (14/14 plans) — completed 2026-05-12
- [x] Phase 3: Games & match types (10/10 plans) — completed 2026-05-13
- [x] Phase 4: Matches (manual) (13/13 plans) — completed 2026-05-13
- [x] Phase 5: Discord bot v1 (13/13 plans) — completed 2026-05-13
- [x] Phase 6: Tournaments & brackets (14/14 plans) — completed 2026-05-14
- [x] Phase 7: CMS (13/13 plans) — completed 2026-05-14
- [x] Phase 8: RCON automation (13/13 plans) — completed 2026-05-14
- [x] Phase 9: Polish (12/12 plans) — completed 2026-05-15

Full details, plan-level breakdown, decisions, deferred items, and operator manual-smoke checklist: [milestones/v1.0-ROADMAP.md](milestones/v1.0-ROADMAP.md).

</details>

<details>
<summary>✅ v1.1 Completion (Phases 10-12) — SHIPPED 2026-06-04</summary>

- [x] Phase 10: Clan applications (7/7 plans) — completed 2026-06-04
- [x] Phase 11: Tournament depth (5/5 plans) — completed 2026-06-04
- [x] Phase 12: Notifications & bot polish (5/5 plans) — completed 2026-06-04

Full details, plan-level breakdown, decisions, and test counts: [milestones/v1.1-ROADMAP.md](milestones/v1.1-ROADMAP.md).

</details>

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Foundations | v1.0 | 18/18 | Complete | 2026-05-04 |
| 2. Clans & tags | v1.0 | 14/14 | Complete | 2026-05-12 |
| 3. Games & match types | v1.0 | 10/10 | Complete | 2026-05-13 |
| 4. Matches (manual) | v1.0 | 13/13 | Complete | 2026-05-13 |
| 5. Discord bot v1 | v1.0 | 13/13 | Complete | 2026-05-13 |
| 6. Tournaments & brackets | v1.0 | 14/14 | Complete | 2026-05-14 |
| 7. CMS | v1.0 | 13/13 | Complete | 2026-05-14 |
| 8. RCON automation | v1.0 | 13/13 | Complete | 2026-05-14 |
| 9. Polish | v1.0 | 12/12 | Complete | 2026-05-15 |
| 10. Clan applications | v1.1 | 7/7 | Complete   | 2026-06-04 |
| 11. Tournament depth | v1.1 | 5/5 | Complete   | 2026-06-04 |
| 12. Notifications & bot polish | v1.1 | 5/5 | Complete   | 2026-06-04 |
| 13. Reachability — MEDIUM gaps | v1.2 | 3/3 | Complete | 2026-06-07 |
| 14. Reachability — LOW gaps | v1.2 | 0/4 | In Progress | — |
