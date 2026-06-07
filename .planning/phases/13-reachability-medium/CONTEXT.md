# Phase 13 Context: Reachability — MEDIUM gaps

**Milestone:** v1.2 Reachability completion
**Goal:** Close the MEDIUM-severity reachability gaps from the 2026-06-06 feature audit.
**Spec source:** ROADMAP.md Phase 13 (skip_discuss=true — ROADMAP goal is the spec).

## Plans

### 13-01 — Applicant withdraw-application UI (REACH-01)
`ClanApplicationService::cancel` (actor==applicant assertion) + `POST /applications/{application}/cancel`
(`applications.cancel`) already work; no UI exposes them. Surface the applicant's own pending
application(s) on `/my-clan` with a Withdraw button, mirroring the received-invites surface added in
the v1.2 HIGH-gap work (MyClanController computes the data for the auth user; MyClan/Index.vue renders
it in every state). Reuse `ClanApplicationData`/add a display DTO with clan name. Feature test:
applicant sees their pending application + can withdraw it.

### 13-02 — Double-elim N≥8 losers-bracket slot collision (REACH-02)
`BracketAdvancementService::resolveSlot($bracket->position)` derives the destination slot from the
SOURCE bracket's position parity only. For N≥8 double-elimination, an LB major round receives two
inbound participants — the previous minor round's winner (via `advances_to_bracket_id`) and a fresh
WB loser (via `loser_advances_to_bracket_id`) — and both can resolve to the same slot, so the second
write overwrites the first and a real participant is dropped. Fix the slot derivation so the two
inbound edges target distinct slots (intended: LB-internal winner → slot A, WB loser drop → slot B,
per DoubleEliminationGenerator's mapping). Add a focused N=8 double-elim repro test that drives a full
losers bracket and asserts no participant is overwritten.

### 13-03 — Public Players index page (REACH-03)
`/players` is linked from `PublicLayout` nav and emitted into `sitemap.xml` (`SitemapGenerateCommand`)
but no `GET /players` route exists (404). Build the public Players index: route + controller +
`Players/Index.vue`, consuming the existing public player projection (privacy-gated via
`PlayerPrivacyGate`, same as the directory pattern). Remove the stale "TODO Phase 9: wire /players
index" comment in PublicLayout. Feature test: `/players` returns 200 and renders the index.

## Constraints
- Container-only commands (D-021). Tests via `make pest` / `docker compose exec web ./vendor/bin/pest`.
- All gates stay green: Pest, PHPStan L8, Pint, vue-tsc. i18n: every UI string via `t()`/`__()`.
- Atomic commit per plan with a SUMMARY.
