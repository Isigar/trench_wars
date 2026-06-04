# Roadmap: Trenchwars

## Milestones

- ✅ **v1.0 Round-1** — Phases 1-9 (shipped 2026-05-17 — see [milestones/v1.0-ROADMAP.md](milestones/v1.0-ROADMAP.md))
- 🚧 **v1.1 Completion** — Phases 10-12 (in progress — started 2026-06-03)

## Phases

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

### v1.1 Completion (Phases 10-12)

- [x] **Phase 10: Clan applications** — Submission flow on web and Discord, eligibility enforcement, and per-clan recruiting toggle
- [x] **Phase 11: Tournament depth** — Swiss auto-advance, ELO seeding, median Buchholz tiebreaker, and stage-level GameMatchType override
- [ ] **Phase 12: Notifications & bot polish** — Notification-preferences account-settings UX and bot list pagination

## Phase Details

### Phase 10: Clan applications
**Goal**: Users can apply to join a clan from the web and from Discord, and clan leaders control whether their clan accepts applications
**Depends on**: Phase 2 (ClanApplication model/observer already present), Phase 5 (bot `/clan apply` stub exists)
**Requirements**: CLAN-01, CLAN-02, CLAN-03, CLAN-04
**Success Criteria** (what must be TRUE):
  1. A logged-in user who is not already in an active clan can submit an application from a clan's public page, and the application appears in the clan's Filament admin view
  2. A user can run `/clan apply <slug>` in Discord and receive confirmation that their application was submitted (the bot calls the web API rather than returning a stub redirect)
  3. Submitting a second application to the same clan while one is pending, or applying when already in an active clan, returns a clear, localized error on both web and Discord — no duplicate or ineligible application is persisted
  4. A clan leader or officer can toggle "accepting applications" on their clan; any application attempt to a closed clan is rejected with a localized reason on both web and Discord
**Plans**: 7 plans
- [x] 10-01-PLAN.md — Schema (accepts_applications column + pending-per-clan partial unique index), Clan model cast, 3 typed exceptions
- [x] 10-02-PLAN.md — ClanApplicationService::apply() with the 3 eligibility guards + service test + web/bot i18n keys
- [x] 10-03-PLAN.md — Bot + web submit controllers, routes, and PHP tests (bot 201/422 + abilities, web feature)
- [x] 10-04-PLAN.md — CLAN-04 recruiting toggle surfaces (ClanData DTO, MyClan settings UI, Filament admin)
- [x] 10-05-PLAN.md — Bot wiring: /clan apply + clan_apply button → api.post, translateError extension, bot tests
- [x] 10-06-PLAN.md — Web "Apply to join" form on the clan show page + eligibility props + feature test
- [x] 10-07-PLAN.md — Phase close: full web+bot gate suite, SC traceability, REQUIREMENTS/ROADMAP updates
**UI hint**: yes

### Phase 11: Tournament depth
**Goal**: Swiss tournaments self-advance, standings use the correct tiebreaker, seeding uses actual player ratings, and stage configuration is more flexible
**Depends on**: Phase 6 (SwissGenerator, StandingsCalculatorService, BracketGenerator, TournamentStage all present)
**Requirements**: TOUR-01, TOUR-02, TOUR-03, TOUR-04
**Success Criteria** (what must be TRUE):
  1. When the final match of a Swiss round has its result recorded, the next round is generated automatically and the tournament page reflects the new round without any admin action
  2. When seeding a tournament bracket, an admin can choose "by rank" and the bracket is seeded by ELO-derived player rating rather than signup order
  3. Swiss standings show median Buchholz alongside plain Buchholz, and the tiebreaker column is visible on the public bracket view
  4. An admin can set a different GameMatchType on an individual tournament stage (overriding the tournament-level default), and matches in that stage are created with the overridden type
**Plans**: 5 plans
- [x] 11-01-PLAN.md — Schema markers (clans.elo_rating/elo_matches_count, brackets.rated_at, standings.median_buchholz, stages.game_match_type_id) + model wiring + DTO field + 4 RED test scaffolds
- [x] 11-02-PLAN.md — Pure logic (TDD): EloRatingService (K=32, base 1500) + median Buchholz (Cut 1) in SwissStandingsCalculator
- [x] 11-03-PLAN.md — BracketAdvancementService hooks: Elo apply (rated_at-guarded, once per bracket) + Swiss auto-advance (idempotent) + by_rank seeding by elo_rating
- [x] 11-04-PLAN.md — TOUR-04 stage override: materialiser stage.game_match_type_id fallback + cross-game-scoped Filament Select + i18n
- [x] 11-05-PLAN.md — Public median Buchholz column + shared-types regen + phase-close full web/bot gate suite + TOUR traceability
**UI hint**: yes

### Phase 12: Notifications & bot polish
**Goal**: Users have full control over which notifications they receive and how, and the Discord bot's list commands support pagination
**Depends on**: Phase 9 (UserNotificationPreference model + NotificationDispatcher present), Phase 5 (bot `/match list` + `/clan list` commands present)
**Requirements**: NOTF-01, BOT-01
**Success Criteria** (what must be TRUE):
  1. A user can open their account-settings page and see all notification event-types with their current preference (in-app / Discord DM / both / none) per channel, change any preference, and have the notification dispatcher honor those choices from that point forward
  2. When `/match list` or `/clan list` returns more results than fit on a single Discord response, the user can navigate to subsequent pages using interactive controls in the same message
**Plans**: 5 plans
  - [x] 12-01-PLAN.md — NOTF-01: notification-preferences nav link (discoverability) + end-to-end honor test
  - [x] 12-02-PLAN.md — BOT-01: list_page customId + Prev/Next pagination button factory (TDD)
  - [x] 12-03-PLAN.md — BOT-01: in-message pagination render in /match list + /clan list (page + "Page X of Y")
  - [ ] 12-04-PLAN.md — BOT-01: Prev/Next handler re-fetches page and interaction.update()s the same message
  - [ ] 12-05-PLAN.md — Phase close: full web + bot gate suites green; trace NOTF-01 + BOT-01 to Met
**UI hint**: yes

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
| 12. Notifications & bot polish | v1.1 | 3/5 | In Progress|  |
