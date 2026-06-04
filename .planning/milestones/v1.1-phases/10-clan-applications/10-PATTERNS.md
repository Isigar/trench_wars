# Phase 10: Clan Applications - Pattern Map

**Mapped:** 2026-06-04
**Files analyzed:** 13 new/modified files
**Analogs found:** 13 / 13

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| migration: add `clans.accepts_applications` + pending-unique index | migration | CRUD | `2026_05_12_100400_create_clan_memberships_table.php` | exact (partial-unique idiom) |
| `app/Exceptions/ClanNotRecruitingException.php` | utility | request-response | `app/Exceptions/MatchNotOpenException.php` | exact |
| `app/Exceptions/AlreadyInClanException.php` | utility | request-response | `app/Exceptions/CapacityExceededException.php` | exact |
| `app/Exceptions/DuplicateApplicationException.php` | utility | request-response | `app/Exceptions/AlreadySignedUpException.php` | exact |
| `app/Services/ClanApplicationService.php` (add `apply()`) | service | CRUD | `app/Services/ClanApplicationService.php` (accept/decline/cancel) | exact |
| `app/Http/Controllers/BotApi/BotApiClanApplicationController.php` | controller | request-response | `app/Http/Controllers/BotApi/BotApiMatchSignupController.php` | exact |
| `app/Http/Controllers/Clans/ClanApplyController.php` (new web submit) | controller | request-response | `app/Http/Controllers/MyClan/ClanApplicationController.php` | role-match |
| `routes/api.php` (add POST bot route) | config | request-response | `routes/api.php` acts-as-user group | exact |
| `routes/web.php` (add POST `/clans/{clan:slug}/apply`) | config | request-response | `routes/web.php` existing `applications.*` entries | exact |
| `resources/js/pages/Clans/Show.vue` (add apply button/form) | component | request-response | `resources/js/pages/Clans/Show.vue` + `resources/js/pages/MyClan/Index.vue` | role-match |
| `apps/bot/src/commands/clan.ts` (swap apply stub → api.post) | service | request-response | `apps/bot/src/commands/clan.ts` info branch + `apps/bot/src/components/rsvpButton.ts` match_signup | exact |
| `apps/bot/src/components/rsvpButton.ts` (swap clan_apply stub + extend translateError) | service | request-response | `apps/bot/src/components/rsvpButton.ts` match_signup branch | exact |
| `lang/en/clans.php` + `lang/en/bot.php` (new keys) | config | - | existing keys in same files | exact |

---

## Pattern Assignments

### Migration: `clans.accepts_applications` column + partial-unique pending index

**Analog:** `apps/web/database/migrations/2026_05_12_100400_create_clan_memberships_table.php`

**Partial-unique index pattern** (lines 46–49):
```php
DB::statement('ALTER TABLE clan_memberships ALTER COLUMN id SET DEFAULT gen_random_uuid();');
DB::statement("ALTER TABLE clan_memberships ADD CONSTRAINT clan_memberships_role_check CHECK (role IN ('leader','officer','member','recruit'));");
// D-009: at most one active membership per user (partial unique index — WHERE clause not supported by Schema builder)
DB::statement('CREATE UNIQUE INDEX clan_memberships_one_active ON clan_memberships (user_id) WHERE left_at IS NULL;');
```

**Second analog for the column addition migration style** — `apps/web/database/migrations/2026_05_14_100100_create_match_slots_table.php` (lines 53–56):
```php
// Partial UNIQUE: a user occupies at most one slot per match (D-009 analog for matches).
// Schema::unique() cannot express WHERE; raw SQL is required (Pitfall 1).
DB::statement('CREATE UNIQUE INDEX match_slots_one_occupancy_per_user ON match_slots (match_id, occupant_user_id) WHERE occupant_user_id IS NOT NULL;');
```

**For this phase** — the new migration needs two `DB::statement` calls:
1. `ALTER TABLE clans ADD COLUMN accepts_applications boolean NOT NULL DEFAULT true;`
2. `CREATE UNIQUE INDEX clan_applications_one_pending_per_clan ON clan_applications (applicant_user_id, clan_id) WHERE status = 'pending';`

The `down()` method must drop the index before the column: `DB::statement('DROP INDEX IF EXISTS clan_applications_one_pending_per_clan;')` followed by `Schema::table('clans', fn ($t) => $t->dropColumn('accepts_applications'));`.

---

### `app/Exceptions/ClanNotRecruitingException.php` (utility, request-response)

**Analog:** `apps/web/app/Exceptions/MatchNotOpenException.php`

**Full pattern** (entire file — 24 lines):
```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
| Thrown by ClanApplicationService::apply() when the target clan has
| accepts_applications = false. Caught by BotApiClanApplicationController
| and mapped to error code 'clan_not_recruiting' / bot.errors.clan_not_recruiting.
|
| Hierarchy: extends \DomainException — matches the ClanApplicationService
| exception family (accept/decline throw \DomainException; apply throws typed
| subclasses so the bot controller can map each to a distinct error code).
*/

final class ClanNotRecruitingException extends \DomainException {}
```

Same one-liner `final class X extends \DomainException {}` pattern for `AlreadyInClanException` and `DuplicateApplicationException`.

---

### `app/Services/ClanApplicationService.php` — add `apply()` method

**Analog:** `apps/web/app/Services/ClanApplicationService.php` — `accept()` method (lines 39–84) and `decline()` (lines 95–116)

**Imports pattern** (lines 1–11 — already present; `apply()` adds Clan):
```php
use App\Models\ClanApplication;
use App\Models\ClanMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
```
Add: `use App\Models\Clan;` + the three new exception imports.

**Guard pattern from `accept()`** (lines 42–63) — mirror exactly but with different guard conditions:
```php
// T-02-07-01 pattern: each guard checks one invariant, throws typed exception.
if ($app->status !== 'pending') {
    throw new \DomainException(__('clans.applications.error.not_pending'));
}

// T-02-07-03: Applicant must not already be in a clan.
$applicantAlreadyMember = ClanMembership::where('user_id', $app->applicant_user_id)
    ->whereNull('left_at')
    ->exists();

if ($applicantAlreadyMember) {
    throw new \DomainException(__('clans.applications.error.already_in_clan'));
}
```

**`apply()` method must follow the same declare order:**
1. Check `$clan->accepts_applications` → throw `ClanNotRecruitingException(__('clans.applications.error.clan_not_recruiting'))`
2. Check active membership exists → throw `AlreadyInClanException(__('clans.applications.error.already_in_clan'))`
3. Check duplicate pending application → throw `DuplicateApplicationException(__('clans.applications.error.duplicate_application'))`
4. `ClanApplication::create([...])` — no DB::transaction needed (single insert)

**Create pattern from `accept()`** (lines 66–83):
```php
return ClanApplication::create([
    'clan_id'            => $clan->id,
    'applicant_user_id'  => $applicant->id,
    'status'             => 'pending',
    'message'            => $message,
]);
```

---

### `app/Http/Controllers/BotApi/BotApiClanApplicationController.php` (controller, request-response)

**Analog:** `apps/web/app/Http/Controllers/BotApi/BotApiMatchSignupController.php` (entire file — copy structure verbatim)

**Imports pattern** (lines 1–22):
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\BotApi;

use App\Data\ClanApplicationData;
use App\Exceptions\AlreadyInClanException;
use App\Exceptions\ClanNotRecruitingException;
use App\Exceptions\DuplicateApplicationException;
use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\User;
use App\Services\ClanApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
```

**Core store() pattern** (lines 55–93 of analog — adapt):
```php
public function store(Clan $clan): JsonResponse
{
    /** @var User $user */
    $user = Auth::user();

    try {
        $application = app(ClanApplicationService::class)->apply($clan, $user);
    } catch (ClanNotRecruitingException) {
        return response()->json([
            'error'   => 'clan_not_recruiting',
            'message' => __('bot.errors.clan_not_recruiting'),
        ], 422);
    } catch (AlreadyInClanException) {
        return response()->json([
            'error'   => 'already_in_clan',
            'message' => __('bot.errors.already_in_clan'),
        ], 422);
    } catch (DuplicateApplicationException) {
        return response()->json([
            'error'   => 'duplicate_application',
            'message' => __('bot.errors.duplicate_application'),
        ], 422);
    }

    return response()->json([
        'data' => ClanApplicationData::fromModel($application),
    ], 201);
}
```

**Key differences from MatchSignupController:**
- No `StoreBotMatchSignupRequest` — the endpoint takes no body in v1 (message is null)
- Route binding is `{clan:slug}` (per `BotApiClanController` and `Clan::getRouteKeyName()`)
- Success envelope is `{ data: ClanApplicationData }` not `{ slot: MatchSlotData }` — see `BotApiClanController::showByDiscordRole()` line 57 for the `{ data: ... }` convention
- Returns 201 (same as MatchSignupController)

---

### `app/Http/Controllers/Clans/ClanApplyController.php` (web submit controller, request-response)

**Analog:** `apps/web/app/Http/Controllers/MyClan/ClanApplicationController.php` (lines 36–52 — the `accept()` method pattern)

**Imports pattern** (lines 1–15):
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clans;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\User;
use App\Services\ClanApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
```

**Core web controller pattern** (lines 36–52 of analog):
```php
public function store(Request $request, Clan $clan, ClanApplicationService $service): RedirectResponse
{
    /** @var User $actor */
    $actor = $request->user();

    $message = $request->string('message')->trim()->value();
    $message = $message === '' ? null : $message;

    try {
        $service->apply($clan, $actor, $message ?: null);
    } catch (\DomainException $e) {
        throw ValidationException::withMessages([
            'application' => [$e->getMessage()],
        ]);
    }

    return redirect()->back()->with('success', __('clans.applications.applied'));
}
```

Note: `ClanShowController::__invoke()` (lines 59–63) shows how the controller passes props to `Inertia::render('Clans/Show', [...])` — the `store()` method on the apply controller redirects back, so the clan show page re-renders with the flash success.

---

### `routes/api.php` — new POST route under acts-as-user group

**Analog:** `apps/web/routes/api.php` lines 46–52 (acts-as-user group)

**Route group to add inside** (lines 46–52):
```php
// Acts-as-user — additional abilities:bot:act-as-user + bot.acts-as middleware.
Route::middleware(['abilities:bot:act-as-user', 'bot.acts-as'])->group(function (): void {
    Route::get('/users/me', [BotApiUserController::class, 'me'])->name('bot.users.me');
    Route::post('/matches/{match}/signups', [BotApiMatchSignupController::class, 'store'])
        ->name('bot.matches.signups.store');
    Route::delete('/matches/{match}/signups/{gameRole}', [BotApiMatchSignupController::class, 'destroy'])
        ->name('bot.matches.signups.destroy');
    // Phase 10 — add here:
    Route::post('/clans/{clan:slug}/applications', [BotApiClanApplicationController::class, 'store'])
        ->name('bot.clans.applications.store');
});
```

---

### `routes/web.php` — new POST `/clans/{clan:slug}/apply`

**Analog:** `apps/web/routes/web.php` existing `applications.*` block (lines 131–143):
```php
// Application management — Leader/Officer accepts or declines pending applications.
Route::post('/applications/{application}/accept', [ClanApplicationController::class, 'accept'])->name('applications.accept');
Route::post('/applications/{application}/decline', [ClanApplicationController::class, 'decline'])->name('applications.decline');
// ...
Route::post('/applications/{application}/cancel', [ClanApplicationController::class, 'cancel'])->name('applications.cancel')
```

The new route goes in the `auth` middleware group (since it requires a logged-in user), outside `my-clan`:
```php
Route::middleware('auth')->group(function (): void {
    Route::post('/clans/{clan:slug}/apply', ClanApplyController::class)->name('clans.apply');
});
```

---

### `resources/js/pages/Clans/Show.vue` — add "Apply to join" form

**Analog:** `apps/web/resources/js/pages/Clans/Show.vue` (entire file — add to existing)

**Imports pattern** (lines 1–14 of Show.vue — extend):
```typescript
import { useForm } from '@inertiajs/vue3';  // add
import { usePage } from '@inertiajs/vue3';  // add for auth().user() check
```

**Props extension** — add to existing `defineProps`:
```typescript
const props = defineProps<{
    clan: ClanData;
    members: ClanMembershipData[];
    hiddenMemberCount: number;
    // Phase 10 additions:
    acceptsApplications: boolean;
    viewerHasPendingApplication: boolean;
    viewerIsActiveMember: boolean;
}>();
```

**useT() + computed pattern** (lines 7, 32–50 of Show.vue):
```typescript
const { t } = useT();
// Visibility guard — show Apply block only when: authed, not active member,
// clan accepts applications, no pending application.
const showApplyBlock = computed(() =>
    page.props.auth?.user !== null
    && props.acceptsApplications
    && !props.viewerIsActiveMember
    && !props.viewerHasPendingApplication
);
```

**Form pattern** — use `useForm` (same pattern as `MyClan/Index.vue`):
```typescript
const applyForm = useForm({ message: '' });
function submitApplication(): void {
    applyForm.post(route('clans.apply', props.clan.slug), {
        preserveScroll: true,
        onSuccess: () => applyForm.reset(),
    });
}
```

**Template addition** after members section — all strings via `t()` (D-013):
```html
<div v-if="showApplyBlock" class="flex flex-col gap-4 pt-4 border-t border-[var(--color-border)]">
    <h2 class="text-xl font-semibold text-[var(--color-text)]">
        {{ t('clans.applications.apply_heading') }}
    </h2>
    <form @submit.prevent="submitApplication" class="flex flex-col gap-3">
        <textarea
            v-model="applyForm.message"
            class="..."
            :placeholder="t('clans.applications.message_placeholder')"
        />
        <button type="submit" :disabled="applyForm.processing">
            {{ t('clans.applications.apply_button') }}
        </button>
        <p v-if="applyForm.errors.application" class="text-red-500 text-sm">
            {{ applyForm.errors.application }}
        </p>
    </form>
</div>
```

---

### `apps/bot/src/commands/clan.ts` — swap apply stub to `api.post`

**Analog:** `apps/bot/src/commands/clan.ts` info branch (lines 61–70) + `apps/bot/src/components/rsvpButton.ts` match_signup branch (lines 67–79)

**Current stub to replace** (lines 81–91):
```typescript
if (sub === 'apply') {
    const slug = interaction.options.getString('slug', true);
    // v1 — redirect to web stub
    await interaction.editReply(
        `Apply via the website: visit the clan page for **${slug}** and click "Apply". ...`,
    );
    return;
}
```

**Replacement pattern** — copy match_signup branch from rsvpButton.ts (lines 67–79):
```typescript
if (sub === 'apply') {
    const slug = interaction.options.getString('slug', true);
    try {
        const { data: application } = await api.post<{ data: unknown }>(
            `/clans/${slug}/applications`,
            {},
            { actsAsDiscordId: interaction.user.id },
        );
        await interaction.editReply('Your application has been submitted.');
    } catch (err) {
        await interaction.editReply(translateError(err));
    }
    return;
}
```

**Import addition** — `translateError` must be imported from `rsvpButton.ts` OR re-exported from a shared util (see rsvpButton.ts lines 29–31 for how it's currently exported):
```typescript
import { translateError } from '../components/rsvpButton.js';
```

---

### `apps/bot/src/components/rsvpButton.ts` — swap clan_apply stub + extend translateError

**Analog:** `apps/bot/src/components/rsvpButton.ts` match_signup branch (lines 67–79) and translateError (lines 120–135)

**Current clan_apply stub to replace** (lines 94–101):
```typescript
if (decoded.kind === 'clan_apply') {
    // v1 redirect-to-web stub
    await interaction.editReply('Clan applications are managed on the website.');
    return;
}
```

**Replacement** — mirror match_signup exactly (lines 67–79):
```typescript
if (decoded.kind === 'clan_apply') {
    try {
        await api.post(
            `/clans/${decoded.clanId}/applications`,
            {},
            { actsAsDiscordId: interaction.user.id },
        );
        await interaction.editReply('Your application has been submitted.');
    } catch (err) {
        await interaction.editReply(translateError(err));
    }
    return;
}
```

Note: `decoded.clanId` — confirm the `clan_apply` variant in `apps/bot/src/lib/customIds.ts` uses `clanId` (not `slug`). If the custom ID encodes a slug, use that. Check `customIds.ts` before writing.

**translateError extension** (lines 120–135 — append 3 new branches before the fallthrough):
```typescript
export function translateError(err: unknown): string {
    const msg = err instanceof Error ? err.message : String(err);
    if (msg.includes('match_not_open'))    return 'This match is not open for signups.';
    if (msg.includes('capacity_full'))     return 'This role is full.';
    if (msg.includes('tag_restricted'))    return 'Your clan tags are not permitted on this match.';
    if (msg.includes('already_signed_up')) return 'You are already signed up to this match.';
    // Phase 10 additions:
    if (msg.includes('clan_not_recruiting'))  return 'This clan is not accepting applications.';
    if (msg.includes('already_in_clan'))      return 'You are already a member of a clan.';
    if (msg.includes('duplicate_application')) return 'You already have a pending application to this clan.';
    return `Failed: ${msg.slice(0, 200)}`;
}
```

---

### i18n: `lang/en/clans.php` — new keys under `applications`

**Analog:** existing `applications` block (lines 136–148):
```php
'applications' => [
    'empty'     => 'No pending applications. Members can apply to join from the clan directory.',
    'accept'    => 'Accept',
    'decline'   => 'Decline',
    'cancel'    => 'Cancel application',
    'accepted'  => 'Application accepted. :name has joined the clan.',
    'declined'  => 'Application declined.',
    'cancelled' => 'Your application has been cancelled.',
    'error' => [
        'not_pending'     => 'This application is no longer pending.',
        'already_in_clan' => 'The applicant is already a member of a clan.',
    ],
],
```

**New keys to add** (extend `error` sub-array + add top-level keys):
```php
// Top-level additions:
'applied'             => 'Your application has been submitted.',
'apply_heading'       => 'Apply to join',
'apply_button'        => 'Submit application',
'message_placeholder' => 'Add a cover message (optional)…',
'not_accepting'       => 'This clan is not currently accepting applications.',

// Error sub-array additions:
'error' => [
    // existing keys...
    'not_pending'            => 'This application is no longer pending.',
    'already_in_clan'        => 'The applicant is already a member of a clan.',
    // new:
    'clan_not_recruiting'    => 'This clan is not accepting applications.',
    'duplicate_application'  => 'You already have a pending application to this clan.',
],
```

Also add to `form` section (CLAN-04 toggle label):
```php
'form' => [
    // existing keys...
    'accepts_applications' => [
        'label' => 'Accept applications',
        'hint'  => 'When disabled, new applications to join this clan will be rejected.',
    ],
],
```

---

### i18n: `lang/en/bot.php` — new error keys

**Analog:** existing `errors` block (lines 21–30):
```php
'errors' => [
    'acts_as_unknown'  => 'Discord user has never logged in to the website.',
    'match_not_open'   => 'This match is not open for signups.',
    'capacity_full'    => 'This role is full.',
    'tag_restricted'   => 'Your clan tags are not permitted on this match.',
    'already_signed_up' => 'You are already signed up to this match.',
    'no_active_clan'   => 'You have no active clan membership.',
    // ...
],
```

**New keys to add**:
```php
'clan_not_recruiting'   => 'This clan is not accepting applications.',
'already_in_clan'       => 'You are already a member of a clan.',
'duplicate_application' => 'You already have a pending application to this clan.',
```

---

### Tests

#### PHP: `tests/Feature/Bot/BotApiClanApplicationAbilitiesTest.php`

**Analog:** `apps/web/tests/Feature/Bot/BotApiMatchSignupAbilitiesTest.php` (full file)

**Test structure pattern** (lines 66–155 of analog):
```php
it('returns 403 when token lacks bot:act-as-user', function (): void {
    $clan = Clan::factory()->create(['accepts_applications' => true]);
    $human = User::factory()->create(['discord_id' => '100000000000000010']);
    $bot = User::factory()->create(['discord_id' => '900000000000000010']);
    $token = $bot->createToken(name: 'bot-test', abilities: ['bot:read'], expiresAt: now()->addDays(30));

    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => $human->discord_id,
        'Accept' => 'application/json',
    ])->postJson("/api/bot/clans/{$clan->slug}/applications")
        ->assertStatus(403);
});
```

Mirror the other 2 ability cases from the analog (missing header → 201, read-only token → 403).

**Fixture helper** — same `botAuthHeaders()` function pattern from `BotApiMatchSignupTest.php` lines 68–83:
```php
function botClanAppHeaders(string $humanDiscordId): array
{
    $bot = User::factory()->create(['discord_id' => '900000000000000099']);
    $token = $bot->createToken(
        name: 'bot-test',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(30),
    );
    return [
        $bot,
        [
            'Authorization' => 'Bearer ' . $token->plainTextToken,
            'X-Bot-Acts-As-User' => $humanDiscordId,
            'Accept' => 'application/json',
        ],
    ];
}
```

#### PHP: `tests/Feature/Bot/BotApiClanApplicationTest.php`

**Analog:** `apps/web/tests/Feature/Bot/BotApiMatchSignupTest.php` (lines 1–80 setup + test cases)

4 test cases to cover:
1. Happy path → 201 + `{ data: ClanApplicationData }`
2. 422 `clan_not_recruiting` when `accepts_applications = false`
3. 422 `already_in_clan` when applicant has active membership
4. 422 `duplicate_application` on second call

**Response shape assertion** (from BotApiClanController analog line 57):
```php
$response->assertStatus(201)
    ->assertJsonStructure(['data' => ['id', 'clan_id', 'applicant_user_id', 'status']]);
```

#### PHP: `tests/Feature/Clans/ClanApplicationTest.php` (extend — add `apply()` cases)

**Analog:** existing `tests/Feature/Clans/ClanApplicationTest.php` (full file — same Pest `it()` format)

**Happy path pattern** (mirror lines 65–91):
```php
it('Authenticated user can submit application to open clan', function (): void {
    $clan = Clan::factory()->create(['accepts_applications' => true]);
    $applicant = User::factory()->create();

    $this->actingAs($applicant)
        ->post(route('clans.apply', $clan->slug))
        ->assertRedirect();

    expect(ClanApplication::where('clan_id', $clan->id)
        ->where('applicant_user_id', $applicant->id)
        ->where('status', 'pending')
        ->exists()
    )->toBeTrue();
});
```

#### Bot: `apps/bot/tests/commands/clan.test.ts` (extend apply describe block)

**Analog:** `apps/bot/tests/commands/clan.test.ts` lines 139–161 — **replace** the existing apply describe block.

**Flip from `not.toHaveBeenCalled()` to `toHaveBeenCalledWith()`** pattern (mirror lines 100–114 of rsvpButton.test.ts):
```typescript
describe('/clan apply subcommand', () => {
    it('calls api.post(/clans/{slug}/applications, {}, actsAsDiscordId)', async () => {
        vi.mocked(api.post).mockResolvedValue({ data: {} });
        const interaction = makeInteraction('apply', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(api.post).toHaveBeenCalledWith(
            '/clans/redwave/applications',
            {},
            { actsAsDiscordId: INVOKER_DISCORD_ID },
        );
    });

    it('editReplies success on api.post success', async () => {
        vi.mocked(api.post).mockResolvedValue({ data: {} });
        const interaction = makeInteraction('apply', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith('Your application has been submitted.');
    });

    it('editReplies translated error on clan_not_recruiting', async () => {
        vi.mocked(api.post).mockRejectedValue(
            new Error('Bot API POST /clans/redwave/applications -> 422: {"error":"clan_not_recruiting"}'),
        );
        const interaction = makeInteraction('apply', { slug: 'redwave' });
        await execute(interaction as unknown as ChatInputCommandInteraction);
        expect(interaction.editReply).toHaveBeenCalledWith(
            'This clan is not accepting applications.',
        );
    });
});
```

#### Bot: `apps/bot/tests/components/rsvpButton.test.ts` (extend clan_apply + translateError)

**Analog:** `apps/bot/tests/components/rsvpButton.test.ts` lines 180–201 + 223–251

**Replace clan_apply describe block** (lines 180–201) — flip from stub assertion to `api.post` assertion (same pattern as match_signup lines 99–146).

**Extend translateError describe** (lines 223–251) — add 3 new `it()` blocks following the existing 4:
```typescript
it('maps clan_not_recruiting', () => {
    const e = new Error('422: {"error":"clan_not_recruiting"}');
    expect(translateError(e)).toBe('This clan is not accepting applications.');
});
it('maps already_in_clan', () => {
    const e = new Error('already_in_clan');
    expect(translateError(e)).toBe('You are already a member of a clan.');
});
it('maps duplicate_application', () => {
    const e = new Error('duplicate_application');
    expect(translateError(e)).toBe('You already have a pending application to this clan.');
});
```

---

## Shared Patterns

### Acts-as-user Bot Auth (applies to: BotApiClanApplicationController, its test, api.php route)
**Source:** `apps/web/app/Http/Controllers/BotApi/BotApiMatchSignupController.php` lines 65–68
```php
/** @var User $user */
$user = Auth::user();
// auth()->user() here is the rebound human, never the bot service account
// (T-05-04-01 mitigation — set upstream by bot.acts-as middleware)
```

### DomainException → ValidationException (applies to: ClanApplyController::store web path)
**Source:** `apps/web/app/Http/Controllers/MyClan/ClanApplicationController.php` lines 41–47
```php
try {
    $service->accept($application, $actor);
} catch (\DomainException $e) {
    throw ValidationException::withMessages([
        'application' => [$e->getMessage()],
    ]);
}
```

### `{ data: … }` JSON envelope (applies to: BotApiClanApplicationController success response, bot api.post call)
**Source:** `apps/web/app/Http/Controllers/BotApi/BotApiClanController.php` lines 55–58
```php
return response()->json([
    'data' => ClanData::fromModel($clan),
]);
```
Bot side — always unwrap `.data` when consuming: `const { data: application } = await api.post<{ data: ClanApplicationData }>(...)`.

### Error code → 422 JSON (applies to: BotApiClanApplicationController)
**Source:** `apps/web/app/Http/Controllers/BotApi/BotApiMatchSignupController.php` lines 69–74
```php
} catch (MatchNotOpenException) {
    return response()->json([
        'error' => 'match_not_open',
        'message' => __('bot.errors.match_not_open'),
    ], 422);
}
```

### Partial unique index raw SQL (applies to: new migration)
**Source:** `apps/web/database/migrations/2026_05_12_100400_create_clan_memberships_table.php` line 49
```php
DB::statement('CREATE UNIQUE INDEX clan_memberships_one_active ON clan_memberships (user_id) WHERE left_at IS NULL;');
// down():
DB::statement('DROP INDEX IF EXISTS clan_memberships_one_active;');
```

### Pest test fixture + actingAs (applies to: PHP tests)
**Source:** `apps/web/tests/Feature/Clans/ClanApplicationTest.php` lines 33–59
```php
function setupApplicationClan(): array { /* ... */ }
// test body:
$this->actingAs($leader)
    ->post(route('my-clan.applications.accept', $app->id))
    ->assertRedirect();
expect($app->fresh()->status)->toBe('accepted');
```

### Bot Vitest mock + makeInteraction (applies to: clan.test.ts, rsvpButton.test.ts)
**Source:** `apps/bot/tests/commands/clan.test.ts` lines 14–21 + 42–56
```typescript
vi.mock('../../src/services/api.js', () => ({
    api: { get: vi.fn(), post: vi.fn(), delete: vi.fn(), request: vi.fn() },
}));
// function makeInteraction(sub, opts) — reuse; do NOT redefine in new describe blocks
```

---

## Clan Model: `accepts_applications` fillable + cast

**File to modify:** `apps/web/app/Models/Clan.php`

**Analog pattern** (lines 52–62 — $fillable array):
```php
protected $fillable = [
    'slug', 'tag', 'name', 'description', 'country_code',
    'owner_user_id', 'status', 'discord_role_id', 'discord_announce_channel_id',
    'accepts_applications',  // add
];
```

Add to `casts()` (currently absent — add method):
```php
protected function casts(): array
{
    return [
        'accepts_applications' => 'boolean',
    ];
}
```

---

## No Analog Found

None — every new file has a strong existing analog in the codebase.

---

## Metadata

**Analog search scope:** `apps/web/app/Services/`, `apps/web/app/Http/Controllers/BotApi/`, `apps/web/app/Http/Controllers/MyClan/`, `apps/web/app/Exceptions/`, `apps/web/database/migrations/`, `apps/web/tests/Feature/`, `apps/web/lang/en/`, `apps/bot/src/commands/`, `apps/bot/src/components/`, `apps/bot/src/services/`, `apps/bot/tests/`
**Files scanned:** 22
**Pattern extraction date:** 2026-06-04
