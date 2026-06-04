# Phase 10: Clan applications - Context

**Gathered:** 2026-06-04
**Status:** Ready for planning
**Mode:** Orchestrator-authored (discuss skipped via workflow.skip_discuss; decisions pre-resolved from prior-session codebase analysis)

<domain>
## Phase Boundary

**Goal:** Users can apply to join a clan from the web and from Discord, and clan leaders control whether their clan accepts applications.

Requirements (REQUIREMENTS.md):
- **CLAN-01** — A logged-in user can submit an application to join a clan from the clan's public web page.
- **CLAN-02** — A user can submit a clan application from Discord via `/clan apply <slug>` (replace the bot's redirect-to-web stub with a live API call).
- **CLAN-03** — Block ineligible applications (applicant already in an active clan, or a duplicate pending application to the same clan) with a clear, localized reason on both web and Discord.
- **CLAN-04** — A clan leader/officer can toggle whether their clan is accepting applications; applications to a closed clan are rejected.

Success criteria: see 10 ROADMAP entry (4 SCs). In scope: the application *submission* flow (web + Discord) + the recruiting toggle. OUT of scope: changing the existing accept/decline/cancel review flow (already shipped in Phase 2).
</domain>

<decisions>
## Implementation Decisions (pre-resolved — orchestrator discretion per skip_discuss)

**KEY FINDING (verified prior session):** The clan-application *submission* path does NOT exist yet. `ClanApplicationService` has only `accept()` / `decline()` / `cancel()`; there is no `apply()`/create method, no web route to submit, and no UI affordance. Phase 2 built the *review* side only. This phase builds the missing *create* side. The bot `/clan apply` is currently a redirect-to-web stub (`apps/bot/src/commands/clan.ts` apply branch + `rsvpButton.ts` clan_apply branch).

1. **Recruiting toggle (CLAN-04):** add a new boolean column `clans.accepts_applications` (migration), default **true** (clans accept applications by default; least friction for an existing league — leaders opt OUT). Fillable on Clan model; editable in the clan's MyClan settings (leader/officer) and surfaced in Filament. Applications to a clan with `accepts_applications=false` are rejected.

2. **Eligibility / duplicate rules (CLAN-03):** reject when —
   - applicant already has an active `ClanMembership` (`left_at IS NULL`) → reuses the D-009 "one active membership" invariant; and
   - applicant already has a `pending` `ClanApplication` for the **same clan** (one pending per (applicant_user_id, clan_id)).
   Enforce in the new service method AND back it with a partial unique index `WHERE status='pending'` (mirror the Phase 2 D-009 partial-unique idiom — raw `CREATE UNIQUE INDEX … WHERE` in a migration; Blueprint can't express WHERE).

3. **Cover message:** OPTIONAL (the `clan_applications.message` column is already nullable; ClanApplication fillable includes `message`). Web form has an optional textarea; Discord `/clan apply` v1 submits with no message (keep the slash command single-arg `<slug>`; a modal for the message is deferred).

4. **New service method:** `ClanApplicationService::apply(Clan $clan, User $applicant, ?string $message = null): ClanApplication` — guards (accepts_applications, not-already-member, no-duplicate-pending) throwing a domain exception per failure mode with an i18n message; creates the `pending` ClanApplication inside the existing LogsActivity flow. Mirror the existing accept/decline/cancel style (DomainException + `__('clans.applications.error.*')`). Consider dedicated exceptions (ClanNotRecruitingException, AlreadyInClanException, DuplicateApplicationException) so the bot controller can map each to a distinct error code — mirror Phase 4/5 typed-exception → 422 pattern.

5. **Web surface (CLAN-01):** a public authenticated controller + route to submit — e.g. `POST /clans/{clan:slug}/apply` → `ClanApplicationController@store` (or a new public controller) calling the service; redirect back with success/validation errors (mirror `MyClan/ClanApplicationController` ValidationException pattern). Add an "Apply to join" button + optional-message form on the clan show page (`ClanShowController` / its Vue page) — visible only when: viewer is authed, not a member of any clan, clan accepts applications, and no pending application exists. i18n via `t()`.

6. **Bot surface (CLAN-02):** new `BotApiClanApplicationController::store(Clan $clan, Request)` under the **acts-as-user** route group (`abilities:bot:act-as-user` + `bot.acts-as`), `POST /api/bot/clans/{clan:slug}/applications` → calls `ClanApplicationService::apply`. Mirror `BotApiMatchSignupController::store` exactly: typed-exception catch blocks → `response()->json(['error'=>'<code>','message'=>__('bot.errors.<code>')], 422)`; success → `{ data: ClanApplicationData::fromModel($app) }` (201). Add `bot.errors.*` keys: `clan_not_recruiting`, `already_in_clan`, `duplicate_application`. Then swap the bot stub: `apps/bot/src/commands/clan.ts` apply branch + `apps/bot/src/components/rsvpButton.ts` clan_apply branch → `api.post('/clans/'+slug+'/applications', {}, { actsAsDiscordId })`; success/error messaging via `translateError` (extend it with the 3 new codes). **Single-object GET/POST envelope:** the success body is `{ data: … }` — if the bot reads it, unwrap `.data` ([[project_bot_web_data_envelope]] gotcha).

7. **DTO:** reuse `ClanApplicationData` for the API success shape (already exists). Regenerate shared-types if the DTO changes (it shouldn't need to).
</decisions>

<code_context>
## Existing Code Insights (patterns to mirror)

- `apps/web/app/Services/ClanApplicationService.php` — accept/decline/cancel; ADD `apply()` here, same DomainException + `__('clans.applications.error.*')` style.
- `apps/web/app/Http/Controllers/MyClan/ClanApplicationController.php` — web controller pattern (service call wrapped in try/catch → `ValidationException::withMessages`).
- `apps/web/app/Http/Controllers/BotApi/BotApiMatchSignupController.php` — **the canonical analog** for the new bot controller (typed-exception → 422 i18n envelope; `Auth::user()` is the acts-as human).
- `apps/web/app/Http/Controllers/BotApi/BotApiClanController.php` — `/clans/{slug}` slug binding + `{ data: … }` envelope convention.
- `apps/web/routes/api.php` — bot route groups; add the POST under the `abilities:bot:act-as-user` + `bot.acts-as` group.
- `apps/web/app/Models/ClanApplication.php` — fillable: clan_id, applicant_user_id, status, message. Statuses: pending→accepted|declined|cancelled. `ClanApplicationObserver` fires `ClanApplicationDecided` notification on decision (not on create — confirm no create-side notification needed for v1.1, or add a leader-facing "new application" notification if cheap; default: no new notification this phase).
- `apps/web/app/Models/Clan.php` — `applications()` HasMany; add `accepts_applications` to fillable + cast bool.
- D-009 partial-unique idiom (Phase 2 ClanMembership) — raw `CREATE UNIQUE INDEX … WHERE` migration for the one-pending-per-clan constraint.
- Bot: `apps/bot/src/commands/clan.ts`, `apps/bot/src/components/rsvpButton.ts` (translateError), `apps/bot/src/services/api.ts` (api.post), `apps/bot/src/lib/customIds.ts`. Bot tests: `apps/bot/tests/commands/clan.test.ts`, `tests/components/rsvpButton.test.ts`.
- i18n: `apps/web/lang/en/clans.php` (applications.* keys) + `apps/web/lang/en/bot.php` (errors.* keys). D-013 — every UI string via `__()`/`t()`; NoHardcodedStringsTest enforces.

## Verification (Docker is available — run the real gates)
- Web: `make pest` (filter to new tests), `make pint`, `make phpstan`, `make artisan ARGS="migrate"` / `migrate:fresh --seed`. Bot: `cd apps/bot && node_modules/.bin/vitest run` + `tsc --noEmit` + `eslint .`.
</code_context>

<specifics>
## Specific Ideas
- Migration adds `clans.accepts_applications` (bool, default true) + the partial-unique pending-application index.
- New typed exceptions in `app/Exceptions/` for clean bot error-code mapping.
- Web Pest: BotApiClanApplicationAbilitiesTest (mirror BotApiMatchSignupAbilitiesTest), a feature test for the web submit flow, and a service test for the 3 guards + happy path. Bot Vitest: update clan apply tests to assert `api.post` is called + success/error messaging (no more stub-string assertion).
</specifics>

<deferred>
## Deferred Ideas
- Discord modal for an optional cover message on `/clan apply` (v1.1 submits message-less from Discord).
- Leader-facing "new application received" notification/Discord ping (only if trivial; otherwise v2).
- Email on application decision (NOTF-V2-01, v2.0).
</deferred>
