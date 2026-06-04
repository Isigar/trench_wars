# Requirements: Trenchwars — v1.1 Completion

**Defined:** 2026-06-03
**Core Value:** Two clans can schedule a scrim, sign up for role slots from Discord, play it on a registered match server, and have a result and per-player events recorded automatically — without manual data entry on the happy path.

## v1.1 Requirements

Requirements for the v1.1 "Completion" milestone — finishing the partially-built and deferred round-1-adjacent features. Each maps to exactly one roadmap phase. Mostly extensions of existing Phase 2/5/6/9 code; web/PHP work is CI-verified (no local Docker — only the Node bot is testable in the dev env).

### Clan applications (CLAN)

- [ ] **CLAN-01**: A logged-in user can submit an application to join a clan from the clan's public web page.
- [ ] **CLAN-02**: A user can submit a clan application from Discord via `/clan apply <slug>` (the bot's redirect-to-web stub is replaced with a live API call).
- [x] **CLAN-03**: The system blocks ineligible applications (applicant already in an active clan, or a duplicate pending application to the same clan) and returns a clear, localized reason on both web and Discord.
- [x] **CLAN-04**: A clan leader/officer can toggle whether their clan is accepting applications; applications to a closed clan are rejected.

### Tournaments (TOUR)

- [ ] **TOUR-01**: A Swiss tournament automatically advances to the next round once every current-round match has a recorded result (no admin click required).
- [ ] **TOUR-02**: An admin can seed a tournament by player rating (ELO-based `by_rank`) using actual match performance, rather than signup order.
- [ ] **TOUR-03**: Swiss standings resolve ties using median Buchholz in addition to plain Buchholz.
- [ ] **TOUR-04**: An admin can override the `GameMatchType` for an individual tournament stage.

### Notifications (NOTF)

- [ ] **NOTF-01**: A user can view and configure their notification preferences (per event-type × channel) in account settings, and the notification dispatcher honors those choices.

### Discord UX (BOT)

- [ ] **BOT-01**: `/match list` and `/clan list` let the user page through results beyond the first page.

## Future Requirements

Deferred to v2.0+ (tracked, not in the v1.1 roadmap). See PROJECT.md "Future Milestones".

### Search & realtime (SRCH / TOUR-V2)

- **SRCH-01**: Meilisearch over Postgres FTS.
- **TOUR-V2-01**: WebSocket live tournament updates (replace 30s polling).

### Notifications & comms (NOTF-V2)

- **NOTF-V2-01**: Email transactional mail provider.
- **NOTF-V2-02**: Notification batching / digest.

### i18n & Discord (I18N / DISC-V2)

- **I18N-V2-01**: CS / SK / PL / DE / RU / FR locale packs.
- **DISC-V2-01**: Bot-managed per-clan voice channels.
- **DISC-V2-02**: Discord-thread links on articles.

### Ops & compliance (OPS-V2)

- **OPS-V2-01**: GDPR right-to-erasure flow (anonymise vs delete).
- **OPS-V2-02**: Activity-log partitioning + 12-month archive.
- **OPS-V2-03**: Monitoring stack (Sentry / Logtail) beyond Railway logs.
- **OPS-V2-04**: Multi-replica rcon-worker + booking lease.

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Native mobile apps | Round-1 decision held; web is mobile-first responsive |
| Real-time spectator overlays | Complexity vs value; not core |
| Anti-cheat / VAC integrations | CRCON-based stats only |
| Federation across multiple league installs | Single-install model intentional |
| Per-clan custom domains | One league domain |
| Email applications/decisions (clan apply) | v1.1 surfaces clan-apply in-app + Discord; email delivery is NOTF-V2-01 (v2.0) |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| CLAN-01 | 10 | Pending |
| CLAN-02 | 10 | Pending |
| CLAN-03 | 10 | Complete |
| CLAN-04 | 10 | Complete |
| TOUR-01 | 11 | Pending |
| TOUR-02 | 11 | Pending |
| TOUR-03 | 11 | Pending |
| TOUR-04 | 11 | Pending |
| NOTF-01 | 12 | Pending |
| BOT-01 | 12 | Pending |

**Coverage:**
- v1.1 requirements: 10 total
- Mapped to phases: 10
- Unmapped: 0 ✓

---
*Requirements defined: 2026-06-03*
*Last updated: 2026-06-03 — roadmap created, all 10 requirements mapped*
