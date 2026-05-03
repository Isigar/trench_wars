# Synthesis Summary

Single entry-point for downstream consumers (gsd-roadmapper). All synthesized intel and the conflicts report are referenced below.

Mode: new (net-new bootstrap)
Precedence applied: ADR > SPEC > PRD > DOC (default; no per-doc overrides)
Cycle detection: passed (no cycles in cross-ref graph)

---

## Doc counts by type

- ADR: 1 (locked)
- SPEC: 11
- PRD: 1
- DOC: 4
- Total ingested: 17

### Per-doc breakdown
- ADR (1): /home/rtx/projects/trench-wars/.docs/15-decisions.md (locked, 20 decisions)
- SPEC (11):
  - /home/rtx/projects/trench-wars/.docs/02-architecture.md
  - /home/rtx/projects/trench-wars/.docs/04-domain-model.md
  - /home/rtx/projects/trench-wars/.docs/05-database-schema.md
  - /home/rtx/projects/trench-wars/.docs/06-permissions-and-audit.md
  - /home/rtx/projects/trench-wars/.docs/07-discord-integration.md
  - /home/rtx/projects/trench-wars/.docs/08-rcon-integration.md
  - /home/rtx/projects/trench-wars/.docs/09-frontend.md
  - /home/rtx/projects/trench-wars/.docs/10-i18n.md
  - /home/rtx/projects/trench-wars/.docs/11-tournaments.md
  - /home/rtx/projects/trench-wars/.docs/12-cms-and-events.md
  - /home/rtx/projects/trench-wars/.docs/13-api-contracts.md
- PRD (1): /home/rtx/projects/trench-wars/.docs/01-overview.md
- DOC (4):
  - /home/rtx/projects/trench-wars/.docs/README.md
  - /home/rtx/projects/trench-wars/.docs/03-stack.md (tech-choice content also lifted into constraints per manifest note)
  - /home/rtx/projects/trench-wars/.docs/14-roadmap.md (M1–M9 milestones lifted into context)
  - /home/rtx/projects/trench-wars/.docs/16-open-questions.md (TBDs lifted into context as Open Questions, advisory only)

---

## Decisions locked

20 decisions, all from /home/rtx/projects/trench-wars/.docs/15-decisions.md, all status `Accepted` (locked).

Decision IDs: D-001 (Laravel+Inertia+Vue), D-002 (Discord OAuth only), D-003 (single league guild), D-004 (bot is thin display layer), D-005 (CRCON), D-006 (multi-clan tenancy), D-007 (relational game model), D-008 (clan tags, no sub-groups), D-009 (one active clan per player), D-010 (signups by role slot), D-011 (tournaments first-class), D-012 (Filament + audit), D-013 (i18n day-one, EN at launch), D-014 (Railway), D-015 (pnpm monorepo, Laravel inside apps/web), D-016 (Postgres 16), D-017 (no Laravel starter kit), D-018 (per-section privacy + global tier), D-019 (CRCON live log + manual override), D-020 (TS types from Laravel DTOs).

See: /home/rtx/projects/trench-wars/.planning/intel/decisions.md

---

## Requirements extracted

16 requirements, all from /home/rtx/projects/trench-wars/.docs/01-overview.md.

IDs: REQ-platform-vision, REQ-tenancy-multi-clan, REQ-tenancy-single-guild, REQ-goal-match-workflows, REQ-goal-rcon-history, REQ-goal-public-profiles, REQ-goal-cms, REQ-goal-discord-ux, REQ-non-goals-round-1, REQ-constraint-league-owns-servers, REQ-constraint-single-guild, REQ-constraint-en-launch-i18n-ready, REQ-constraint-railway-deploy, REQ-success-end-to-end-scrim, REQ-success-tournament-end-to-end, REQ-success-public-browse.

See: /home/rtx/projects/trench-wars/.planning/intel/requirements.md

---

## Constraints

~85 constraint entries from 11 SPECs plus tech-choice content from 03-stack.md.

Type breakdown (approximate):
- protocol: ~50 (service topology, auth flows, lifecycle protocols, library bindings, deployment, slash commands, role sync, RCON session lifecycle, CMS publishing flow, i18n procedures, tournament lifecycle, API conventions, etc.)
- schema: ~30 (DB tables, domain entities, component inventory, page tree, folder structure, theme tokens, event normalisation, layouts)
- api-contract: ~15 (REST endpoints, internal endpoints, Discord event hooks, bot↔web auth headers, HMAC signing protocol)
- nfr: ~6 (audit retention, frontend goals, performance, accessibility, Discord security, RCON security)

See: /home/rtx/projects/trench-wars/.planning/intel/constraints.md

---

## Context topics

5 topics:
1. Project framing + index of source planning docs
2. Glossary
3. Why no Laravel starter kit (rationale)
4. Existing M1–M9 roadmap (round 1 starting point) — all 9 milestones with outcomes and item lists
5. Open Questions (TBDs from 16-open-questions.md) — 9 categories, advisory only

See: /home/rtx/projects/trench-wars/.planning/intel/context.md

---

## Conflicts

- BLOCKERS: 0
- WARNINGS (competing variants): 0
- INFO (auto-resolved / advisory): 4

INFO entries:
1. Single LOCKED ADR — no LOCKED-vs-LOCKED contradictions
2. No cross-reference cycles
3. Tech-stack content from 03-stack.md treated as constraints (no precedence conflict with ADR)
4. 16-open-questions.md TBDs surfaced advisory, not blocker

See: /home/rtx/projects/trench-wars/.planning/INGEST-CONFLICTS.md

---

## Artifact pointers

- Decisions: /home/rtx/projects/trench-wars/.planning/intel/decisions.md
- Requirements: /home/rtx/projects/trench-wars/.planning/intel/requirements.md
- Constraints: /home/rtx/projects/trench-wars/.planning/intel/constraints.md
- Context: /home/rtx/projects/trench-wars/.planning/intel/context.md
- Conflicts report: /home/rtx/projects/trench-wars/.planning/INGEST-CONFLICTS.md
- This summary: /home/rtx/projects/trench-wars/.planning/intel/SYNTHESIS.md

Status: READY — safe to route to gsd-roadmapper. No blockers, no competing variants requiring user resolution.
