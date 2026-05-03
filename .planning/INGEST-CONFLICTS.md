## Conflict Detection Report

### BLOCKERS (0)

(none)

### WARNINGS (0)

(none)

### INFO (4)

[INFO] Single LOCKED ADR — no LOCKED-vs-LOCKED contradictions
  Note: Only one ADR-class document was ingested (/home/rtx/projects/trench-wars/.docs/15-decisions.md). It carries 20 Accepted decisions (D-001 through D-020). Because there is no second locked ADR in the set, the LOCKED-vs-LOCKED contradiction check passes trivially. All 20 decisions written verbatim into /home/rtx/projects/trench-wars/.planning/intel/decisions.md.

[INFO] No cross-reference cycles
  Note: Cross-ref graph (cross_refs from each classification) was DFS-traversed with three-color marking. No cycles detected. Edges: 02-architecture → 13-api-contracts; 03-stack → 09-frontend; 04-domain-model → 05-database-schema; 15-decisions → 07-discord-integration; 16-open-questions → 15-decisions; README → all numbered docs (one-way fanout). Max depth observed: 2.

[INFO] Tech-stack content from /home/rtx/projects/trench-wars/.docs/03-stack.md treated as constraints
  Note: 03-stack.md was classifier-tagged DOC (high confidence) but the ingest manifest flagged its tech-choice content as locked. Content was extracted into /home/rtx/projects/trench-wars/.planning/intel/constraints.md as protocol-type constraints (CON-stack-versions, CON-stack-web-libraries, CON-stack-frontend-libraries, CON-stack-bot-libraries, CON-stack-rcon-libraries, CON-stack-tooling). Narrative rationale ("why no starter kit") was placed into /home/rtx/projects/trench-wars/.planning/intel/context.md. No precedence conflict: every stack item is consistent with D-001 (Laravel + Inertia + Vue), D-014 (Railway), D-015 (pnpm monorepo), D-016 (Postgres 16), D-017 (no starter kit), D-020 (TS DTO generation).

[INFO] /home/rtx/projects/trench-wars/.docs/16-open-questions.md TBDs surfaced advisory, not blocker
  Note: 16-open-questions.md is DOC-classified (high confidence). It contains unresolved planning questions across branding, editorial, matches/tournaments, Discord, RCON, data retention, locales, ops, and naming conventions. Per ingest manifest these are advisory and were copied verbatim into /home/rtx/projects/trench-wars/.planning/intel/context.md under "Open Questions". They do NOT gate the workflow. Downstream roadmapper should surface them in PROJECT.md as known gaps and capture answers as new D-### decisions.
