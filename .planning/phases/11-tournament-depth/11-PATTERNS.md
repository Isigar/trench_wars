# Phase 11: Tournament depth - Pattern Map

**Mapped:** 2026-06-04
**Files analyzed:** 13 new/modified files
**Analogs found:** 13 / 13

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `app/Services/EloRatingService.php` (NEW) | service | transform | `app/Services/ClanSlugGenerator.php` | role-match (stateless pure service) |
| `app/Services/BracketAdvancementService.php` (MODIFY) | service | event-driven | self — extend existing | exact |
| `app/Services/Brackets/SwissGenerator.php` (MODIFY) | service | event-driven | self — extend existing | exact |
| `app/Services/Standings/SwissStandingsCalculator.php` (MODIFY) | service | transform | self — extend existing | exact |
| `app/Services/TournamentSeedingService.php` (MODIFY) | service | CRUD | self — extend existing | exact |
| `app/Services/BracketMatchMaterialiserService.php` (MODIFY) | service | CRUD | self — extend existing | exact |
| `database/migrations/…_add_elo_rating_to_clans.php` (NEW) | migration | — | `database/migrations/2026_06_04_100000_add_accepts_applications_to_clans.php` | exact |
| `database/migrations/…_add_rated_at_to_tournament_brackets.php` (NEW) | migration | — | `database/migrations/2026_06_04_100000_add_accepts_applications_to_clans.php` | exact |
| `database/migrations/…_add_median_buchholz_to_tournament_standings.php` (NEW) | migration | — | `database/migrations/2026_05_15_100400_create_tournament_standings_table.php` | role-match |
| `database/migrations/…_add_game_match_type_id_to_tournament_stages.php` (NEW) | migration | — | `database/migrations/2026_06_04_100000_add_accepts_applications_to_clans.php` | exact |
| `app/Data/TournamentStandingData.php` (MODIFY) | DTO | transform | self — extend existing | exact |
| `resources/js/components/tournaments/StandingsTable.vue` (MODIFY) | component | request-response | self — extend existing | exact |
| `app/Filament/Resources/TournamentResource/RelationManagers/StagesRelationManager.php` (MODIFY) | Filament relation manager | CRUD | `app/Filament/Resources/GameMatchTypeResource/RelationManagers/RoleLimitsRelationManager.php` | role-match (scoped Select) |

---

## Pattern Assignments

### `app/Services/EloRatingService.php` (NEW — stateless pure service)

**Analog:** `app/Services/ClanSlugGenerator.php`

**Imports / class structure** (lines 1–17):
```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReservedSlugException;
use App\Models\Clan;
use Illuminate\Support\Str;

/*
| Stateless service — auto-resolved by the Laravel container.
*/

final class ClanSlugGenerator
{
    public function generate(string $name): string
    {
```

**Pattern to copy:** `final class`, `declare(strict_types=1)`, `namespace App\Services`, no constructor injection needed (pure math). For `EloRatingService`, inject nothing — use `DB::transaction` inline. Method signature:
```php
public function applyResult(Clan $winner, Clan $loser, bool $draw = false): void
```

**Transaction + idempotency guard** — copy the `DB::transaction` + `lockForUpdate` idiom from `BracketAdvancementService` (lines 120–122):
```php
DB::transaction(function () use ($bracket, $winnerParticipant, $loserParticipant, $tournament): void {
    Tournament::query()->whereKey($tournament->id)->lockForUpdate()->first();
    // ...
});
```
For `EloRatingService::applyResult`, adapt to lock BOTH clan rows:
```php
DB::transaction(function () use ($winner, $loser, $draw): void {
    $w = Clan::query()->whereKey($winner->id)->lockForUpdate()->firstOrFail();
    $l = Clan::query()->whereKey($loser->id)->lockForUpdate()->firstOrFail();
    // Elo math here; save with ->update(['elo_rating' => ..., 'elo_matches_count' => ...])
});
```

**Activity log pattern** — copy from `TournamentSeedingService` lines 105–113:
```php
activity()
    ->causedBy($causer ?? auth()->user())
    ->performedOn($tournament)
    ->withProperties([
        'strategy' => $strategy,
        'participant_count' => $count,
    ])
    ->log('Tournament seeded');
```

---

### `app/Services/BracketAdvancementService.php` (MODIFY — add Elo hook + Swiss auto-advance)

**File:** `app/Services/BracketAdvancementService.php`

**Hook point for Elo (after step 1, before step 2)** — lines 124–126 show where `winner_participant_id` is written. Elo fires immediately after this, inside the same transaction. Participant→Clan resolution already exists at lines 99–109 (`$winnerParticipant`) and lines 112–118 (`$loserParticipant`). The two clan IDs are `$winnerParticipant->clan_id` / `$loserParticipant->clan_id`. Fetch the Clan models and call `EloRatingService::applyResult`.

**Idempotency guard for Elo** — mirror the grand-final reset guard (lines 172–179):
```php
$existingReset = TournamentBracket::query()
    ->where('tournament_stage_id', $stage->id)
    ->where('round_number', 2)
    ->where('position', 1)
    ->first();

if ($existingReset === null) {
    TournamentBracket::create([...]);
}
```
For Elo: check `$bracket->rated_at === null` before calling `applyResult`, then set `$bracket->update(['rated_at' => now()])` inside the transaction.

**Swiss auto-advance hook point** — insert after step 5 (standings recalc, line 187) and before step 6 (Discord announce). Pattern:
```php
// 5a. Swiss auto-advance — if this is a swiss-round stage and all its brackets
//     are decided, generate the next round automatically (TOUR-01).
if ($stage->type === 'swiss-round') {
    $roundComplete = ! TournamentBracket::query()
        ->where('tournament_stage_id', $stage->id)
        ->whereNull('winner_participant_id')
        ->exists();

    $nextRoundExists = $tournament->stages()
        ->where('type', 'swiss-round')
        ->where('ordinal', '>', $stage->ordinal)  // requires stage.ordinal on the loaded stage
        ->exists();

    if ($roundComplete && ! $nextRoundExists) {
        // Guard against LogicException (rounds exhausted) — wrap in try/catch or
        // check currentRound < totalRounds inline (copy SwissGenerator::generateNextRound
        // exhaustion check).
        app(\App\Services\Brackets\SwissGenerator::class)->generateNextRound($tournament);
    }
}
```
Use `app()` lookup (same circular-DI break documented at lines 51–53).

**Circular-DI break comment** (lines 51–53) — copy this doc comment style for the EloRatingService and SwissGenerator injections:
```php
// Circular-DI break (T-06-08-07) — StandingsCalculatorService is resolved via
// app() lookup, NOT constructor injection.
app(StandingsCalculatorService::class)->recalculate($tournament);
```

---

### `app/Services/Brackets/SwissGenerator.php` (MODIFY — round-complete detection already used by auto-advance; generateNextRound is the target method)

**File:** `app/Services/Brackets/SwissGenerator.php`

**Round exhaustion check** (lines 151–159) — the auto-advance caller in BracketAdvancementService must replicate this guard to avoid calling generateNextRound when rounds are exhausted:
```php
$currentRound = $tournament->stages()->where('type', 'swiss-round')->max('ordinal');
$activeCount = $tournament->participants()->where('status', 'active')->count();
$totalRounds = max(1, (int) ceil(log(max($activeCount, 2), 2)));

if ($currentRound === null || $currentRound >= $totalRounds) {
    throw new LogicException(
        (string) __('tournaments.errors.swiss_rounds_exhausted')
    );
}
```

**New stage creation** (lines 225–231) — auto-generated round uses the same `TournamentStage::create` call:
```php
$stage = TournamentStage::create([
    'tournament_id' => $tournament->id,
    'type' => 'swiss-round',
    'ordinal' => $nextOrdinal,
    'name' => (string) __('tournaments.stage_types.swiss-round.label') . ' ' . $nextOrdinal,
    'settings' => null,
]);
```

No changes are needed to `generateNextRound` itself — the auto-advance path calls it unchanged. The Filament HeaderAction (plan 06-11) stays as a manual fallback.

---

### `app/Services/Standings/SwissStandingsCalculator.php` (MODIFY — add median Buchholz)

**File:** `app/Services/Standings/SwissStandingsCalculator.php`

**Existing Buchholz pass** (lines 151–159) to extend with median:
```php
// Second pass: Buchholz = sum of each participant's opponents' final points.
/** @var array<string, float> $buchholzByParticipant */
$buchholzByParticipant = [];
foreach ($participants as $p) {
    $sum = 0.0;
    foreach ($opponentsByParticipant[$p->id] as $oppId) {
        $sum += $pointsByParticipant[$oppId] ?? 0.0;
    }
    $buchholzByParticipant[$p->id] = $sum;
}
```

**Median Buchholz to add** — after the plain Buchholz pass, add a parallel pass:
```php
/** @var array<string, float> $medianBuchholzByParticipant */
$medianBuchholzByParticipant = [];
foreach ($participants as $p) {
    $opponentScores = [];
    foreach ($opponentsByParticipant[$p->id] as $oppId) {
        $opponentScores[] = $pointsByParticipant[$oppId] ?? 0.0;
    }
    // Median Buchholz (Buchholz Cut 1): drop highest + lowest opponent score.
    // For < 3 opponents, equals plain Buchholz (no values to drop).
    if (count($opponentScores) >= 3) {
        sort($opponentScores);
        array_shift($opponentScores); // drop lowest
        array_pop($opponentScores);   // drop highest
    }
    $medianBuchholzByParticipant[$p->id] = array_sum($opponentScores);
}
```

**Row insertion** (lines 162–200) — extend the `TournamentStanding::create` call to include `median_buchholz`:
```php
TournamentStanding::create([
    'tournament_id' => $tournament->id,
    'tournament_stage_id' => $primaryStage->id,
    'participant_id' => $row['participant']->id,
    'wins' => $row['wins'],
    'losses' => $row['losses'],
    'draws' => $row['draws'],
    'points' => $row['points'],
    'tiebreak_score' => $row['buchholz'],
    'rank' => $index + 1,
    // NEW:
    'median_buchholz' => $medianBuchholzByParticipant[$row['participant']->id],
]);
```

**Sort order** (lines 173–184) — add median_buchholz as third tiebreaker after buchholz:
```php
usort($rows, function (array $a, array $b): int {
    if ((float) $a['points'] !== (float) $b['points']) {
        return $b['points'] <=> $a['points'];
    }
    if ((float) $a['buchholz'] !== (float) $b['buchholz']) {
        return $b['buchholz'] <=> $a['buchholz'];
    }
    // NEW third tiebreaker: median_buchholz DESC
    if ((float) $a['median_buchholz'] !== (float) $b['median_buchholz']) {
        return $b['median_buchholz'] <=> $a['median_buchholz'];
    }
    $aSeed = $a['participant']->seed ?? PHP_INT_MAX;
    $bSeed = $b['participant']->seed ?? PHP_INT_MAX;
    return $aSeed <=> $bSeed;
});
```

---

### `app/Services/TournamentSeedingService.php` (MODIFY — by_rank uses clan.elo_rating)

**File:** `app/Services/TournamentSeedingService.php`

**Target method** (lines 177–179):
```php
private function orderByRank(EloquentCollection $participants): Collection
{
    return $participants->sortByDesc('created_at')->values();
}
```

**Replacement** — eager-load the clan relation (already available via `TournamentParticipant::clan()` BelongsTo at line 76 of `TournamentParticipant.php`) and sort by `elo_rating DESC`, tiebreak `created_at ASC`:
```php
private function orderByRank(EloquentCollection $participants): Collection
{
    // Phase 11: order by clan.elo_rating DESC (previously created_at DESC proxy).
    // Tiebreak on created_at ASC for determinism when all clans have equal rating
    // (e.g. all 1500 at tournament start — degrades to stable insertion order).
    $participants->loadMissing('clan');

    return $participants
        ->sortBy([
            fn ($a, $b) => ($b->clan?->elo_rating ?? 1500) <=> ($a->clan?->elo_rating ?? 1500),
            fn ($a, $b) => $a->created_at <=> $b->created_at,
        ])
        ->values();
}
```

No changes to `seed()`, `reseed()`, or `canReseed()` semantics (D-06-05-A locked).

---

### `app/Services/BracketMatchMaterialiserService.php` (MODIFY — stage.game_match_type_id override)

**File:** `app/Services/BracketMatchMaterialiserService.php`

**Current match type resolution** (lines 141–145):
```php
if ($t->default_game_match_type_id === null) {
    throw new RuntimeException(
        "Tournament {$t->id} has no default_game_match_type_id — cannot materialise bracket GameMatch."
    );
}

$match = GameMatch::create([
    ...
    'game_match_type_id' => $t->default_game_match_type_id,
    ...
]);
```

**Extended resolution** — load the bracket's stage and prefer stage-level override:
```php
// TOUR-04: use stage-level GameMatchType override if set, else tournament default.
// Load stage if not already loaded (materialiseFor may receive a freshly-fetched bracket).
$stage = $locked->stage ?? $locked->stage()->first();
$effectiveMatchTypeId = ($stage?->game_match_type_id) ?? $t->default_game_match_type_id;

if ($effectiveMatchTypeId === null) {
    throw new RuntimeException(
        "Tournament {$t->id} has no default_game_match_type_id and stage has no override — cannot materialise bracket GameMatch."
    );
}

$match = GameMatch::create([
    ...
    'game_match_type_id' => $effectiveMatchTypeId,
    ...
]);
```

---

## Schema Migration Patterns

### `add_elo_rating_to_clans` migration

**Analog:** `app/Services/BracketMatchMaterialiserService.php` — no; migration analog is `database/migrations/2026_06_04_100000_add_accepts_applications_to_clans.php`

**Pattern** (lines 33–36 of the `add_accepts_applications` migration):
```php
Schema::table('clans', function (Blueprint $table): void {
    $table->boolean('accepts_applications')->default(true)->after('status');
});
```

**For elo_rating:**
```php
Schema::table('clans', function (Blueprint $table): void {
    $table->integer('elo_rating')->default(1500)->after('status');
    $table->integer('elo_matches_count')->default(0)->after('elo_rating');
});
```

`down()` drops columns in REVERSE order (line 47–50 of the analog):
```php
public function down(): void
{
    Schema::table('clans', function (Blueprint $table): void {
        $table->dropColumn(['elo_matches_count', 'elo_rating']);
    });
}
```

Also add `elo_rating` and `elo_matches_count` to `Clan::$fillable` and `Clan::casts()` (integer cast).

---

### `add_rated_at_to_tournament_brackets` migration

**Analog:** same `add_accepts_applications_to_clans.php` idiom.

```php
Schema::table('tournament_brackets', function (Blueprint $table): void {
    $table->timestampTz('rated_at')->nullable()->after('winner_participant_id');
});

// No partial-unique needed — rated_at is a nullable timestamp marker only.
```

`down()`:
```php
Schema::table('tournament_brackets', function (Blueprint $table): void {
    $table->dropColumn('rated_at');
});
```

Also add `'rated_at'` to `TournamentBracket::$fillable` and cast `'rated_at' => 'datetime'`.

---

### `add_median_buchholz_to_tournament_standings` migration

**Analog:** `database/migrations/2026_05_15_100400_create_tournament_standings_table.php` (line 46 — `decimal(8,2)` column).

```php
Schema::table('tournament_standings', function (Blueprint $table): void {
    // Match the existing points/tiebreak_score precision: decimal(8,2).
    $table->decimal('median_buchholz', 8, 2)->default(0)->after('tiebreak_score');
});
```

`down()`:
```php
Schema::table('tournament_standings', function (Blueprint $table): void {
    $table->dropColumn('median_buchholz');
});
```

Also add `'median_buchholz'` to `TournamentStanding::$fillable` and cast `'median_buchholz' => 'decimal:2'`.

---

### `add_game_match_type_id_to_tournament_stages` migration

**Analog:** `add_accepts_applications_to_clans.php` + FK-with-nullOnDelete pattern from `create_tournament_brackets_table.php`.

```php
Schema::table('tournament_stages', function (Blueprint $table): void {
    $table->uuid('game_match_type_id')->nullable()->after('settings');
    $table->foreign('game_match_type_id')
        ->references('id')
        ->on('game_match_types')
        ->nullOnDelete();
});
```

`down()`:
```php
Schema::table('tournament_stages', function (Blueprint $table): void {
    $table->dropForeign(['game_match_type_id']);
    $table->dropColumn('game_match_type_id');
});
```

Also add `'game_match_type_id'` to `TournamentStage::$fillable`.

---

## DTO and Vue Patterns

### `app/Data/TournamentStandingData.php` (MODIFY — add median_buchholz)

**File:** `app/Data/TournamentStandingData.php`

**Existing field pattern** (lines 35–37):
```php
public float $tiebreak_score,
public ?int $rank,
```

**Add** `public float $median_buchholz` after `tiebreak_score`. In `fromModel` (line 50):
```php
tiebreak_score: (float) $standing->tiebreak_score,
median_buchholz: (float) $standing->median_buchholz,  // NEW
rank: $standing->rank,
```

All `#[TypeScript]` DTO fields auto-generate into `packages/shared-types/` via `php artisan typescript:transform` — no manual TS file editing needed.

---

### `resources/js/components/tournaments/StandingsTable.vue` (MODIFY — median Buchholz column)

**File:** `resources/js/components/tournaments/StandingsTable.vue`

**Existing tiebreak label pattern** (lines 24–28):
```typescript
const tiebreakLabel = computed<string>(() => {
    if (props.format === 'swiss') return t('tournaments.standings.tiebreak_buchholz');
    if (props.format === 'round_robin') return t('tournaments.standings.tiebreak_point_diff');
    return t('tournaments.standings.tiebreak_default');
});
```

**New computed for median** — add alongside `tiebreakLabel`:
```typescript
const showMedianBuchholz = computed<boolean>(() => props.format === 'swiss');
```

**Template** — add column header (after the existing Buchholz `<th>`) and data cell, following the same pattern as lines 64 + 79:
```html
<th v-if="showMedianBuchholz" class="text-right px-3 py-2 font-semibold">
    {{ t('tournaments.standings.tiebreak_median_buchholz') }}
</th>
<!-- in tbody tr: -->
<td v-if="showMedianBuchholz" class="px-3 py-2 text-right">
    {{ (row as any).median_buchholz ?? '—' }}
</td>
```

---

### `app/Filament/Resources/TournamentResource/RelationManagers/StagesRelationManager.php` (MODIFY — game_match_type_id Select)

**File:** `app/Filament/Resources/TournamentResource/RelationManagers/StagesRelationManager.php`

**Cross-game scoped Select — Pattern 3 from RoleLimitsRelationManager** (lines 56–80):
```php
Forms\Components\Select::make('game_role_id')
    ->label(__('admin.game_match_type_role_limit.fields.role'))
    ->options(function (RelationManager $livewire): array {
        /** @var GameMatchType $matchType */
        $matchType = $livewire->getOwnerRecord();
        $game = $matchType->game;

        if ($game === null) {
            return [];
        }

        return $game->roles()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(function ($role): array { ... })
            ->toArray();
    })
```

**Adapt for stage game_match_type_id** — the owner record is the TournamentStage; traverse `$stage->tournament->game->matchTypes()`:
```php
Forms\Components\Select::make('game_match_type_id')
    ->label(__('admin.tournament_stage.fields.game_match_type_id'))
    ->options(function (RelationManager $livewire): array {
        /** @var TournamentStage $stage */
        $stage = $livewire->getOwnerRecord();
        $game = $stage->tournament?->game;

        if ($game === null) {
            return [];
        }

        return $game->matchTypes()
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn ($mt): array => [$mt->id => $mt->key])
            ->toArray();
    })
    ->nullable()
    ->searchable()
```

The StagesRelationManager currently has NO EditAction (read-only per T-06-11-04). To add the game_match_type_id override without breaking the read-only invariant, make only `game_match_type_id` editable via a standalone `EditAction` restricted to that field, or add it as a Filament `Action` on the row — see plan for the appropriate scoping decision.

Also add `'game_match_type_id'` to the table as a `TextColumn::make('defaultGameMatchType.key')->label(...)->placeholder('—')`.

---

## Shared Patterns

### DB::transaction + lockForUpdate (Pitfall 6)
**Source:** `app/Services/BracketAdvancementService.php` lines 120–122
**Apply to:** `EloRatingService::applyResult` (lock both clan rows), BracketAdvancementService extensions
```php
DB::transaction(function () use (...): void {
    Tournament::query()->whereKey($tournament->id)->lockForUpdate()->first();
    // ... all writes inside the closure
});
```

### Idempotency guard (existence check before write)
**Source:** `app/Services/BracketAdvancementService.php` lines 172–180 (grand-final reset guard)
**Apply to:** Elo `rated_at` check, Swiss auto-advance next-round check
```php
$existingReset = TournamentBracket::query()
    ->where('tournament_stage_id', $stage->id)
    ->where('round_number', 2)
    ->where('position', 1)
    ->first();

if ($existingReset === null) { /* create */ }
```

### Circular-DI break via app() lookup
**Source:** `app/Services/BracketAdvancementService.php` line 187
**Apply to:** SwissGenerator call from BracketAdvancementService, EloRatingService call from BracketAdvancementService
```php
app(StandingsCalculatorService::class)->recalculate($tournament);
```

### Partial UNIQUE index (raw DB::statement)
**Source:** `database/migrations/2026_05_12_100400_create_clan_memberships_table.php` lines 49–50
**Apply to:** Any new conditional constraint (none expected in Phase 11 — the `rated_at` idempotency marker is a nullable nullable column, not an index)
```php
DB::statement('CREATE UNIQUE INDEX clan_memberships_one_active ON clan_memberships (user_id) WHERE left_at IS NULL;');
```

### Activity log (spatie/activitylog)
**Source:** `app/Services/TournamentSeedingService.php` lines 105–113
**Apply to:** `EloRatingService::applyResult` (log the rating delta)
```php
activity()
    ->causedBy($causer ?? auth()->user())
    ->performedOn($tournament)
    ->withProperties(['strategy' => $strategy, 'participant_count' => $count])
    ->log('Tournament seeded');
```

### i18n — every new label string
**Source:** `app/Services/Brackets/SwissGenerator.php` line 88
**Apply to:** all new admin field labels, error messages, Vue column headers
```php
(string) __('tournaments.stage_types.swiss-round.label')
// Vue: t('tournaments.standings.tiebreak_median_buchholz')
```

---

## Pest Test Patterns

### Unit test for pure math (EloRatingService, median Buchholz)
**Analog:** `tests/Feature/Services/TournamentSeedingServiceTest.php` (structure) + `tests/Feature/Services/BracketGeneratorSwissTest.php` (factory helpers)

**File/structure pattern** (BracketGeneratorSwissTest lines 1–47):
```php
<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Services\EloRatingService;

/*
| Source: 11-xx-PLAN.md Task N — EloRatingService unit tests.
|
| Covers: K=32 formula, draw, idempotency (rated_at guard),
|         all-1500 degrades to no-change.
*/

it('winner gains rating and loser loses rating (K=32)', function (): void {
    $winner = Clan::factory()->create(['elo_rating' => 1500]);
    $loser  = Clan::factory()->create(['elo_rating' => 1500]);

    app(EloRatingService::class)->applyResult($winner, $loser);

    expect($winner->fresh()->elo_rating)->toBeGreaterThan(1500);
    expect($loser->fresh()->elo_rating)->toBeLessThan(1500);
});
```

### Seeding regression test (by_rank uses elo_rating)
**Analog:** `tests/Feature/Services/TournamentSeedingServiceTest.php` lines 35–50
```php
it('by_rank strategy orders participants by clan elo_rating desc', function (): void {
    $tournament = Tournament::factory()->inStatus('registering')->create();

    // High-elo clan → seed 1.
    $highElo = Clan::factory()->create(['elo_rating' => 1800]);
    $lowElo  = Clan::factory()->create(['elo_rating' => 1200]);

    $p1 = TournamentParticipant::factory()->for($tournament)->create(['clan_id' => $highElo->id]);
    $p2 = TournamentParticipant::factory()->for($tournament)->create(['clan_id' => $lowElo->id]);

    app(TournamentSeedingService::class)->seed($tournament, 'by_rank');

    expect($p1->fresh()->seed)->toBe(1);
    expect($p2->fresh()->seed)->toBe(2);
});
```

### Auto-advance integration test
**Analog:** `tests/Feature/Services/BracketAdvancementServiceTest.php` structure (makeAdvancementTournament + makeAdvancementParticipants helpers at lines 45–80).

Key assertions to copy:
- Assert `stages()->where('type','swiss-round')->count()` increments after the final result of a round is recorded.
- Assert no second round is generated on a second call (idempotency).

### Median Buchholz standings test
**Analog:** `tests/Feature/Services/StandingsCalculatorServiceTest.php` lines 40–80 (makeStandingsTournament + makeStandingsParticipants helpers).

Add to `StandingsCalculatorServiceTest`: fixture a 4-participant Swiss round where opponent scores are known, assert `median_buchholz` value; assert `<3 opponents → median_buchholz == buchholz`.

---

## No Analog Found

All Phase 11 files have close analogs in the existing Phase 6 codebase. No files fall into the "no analog" category.

---

## Metadata

**Analog search scope:** `apps/web/app/Services/`, `apps/web/app/Services/Brackets/`, `apps/web/app/Services/Standings/`, `apps/web/app/Models/`, `apps/web/database/migrations/`, `apps/web/app/Data/`, `apps/web/resources/js/components/tournaments/`, `apps/web/app/Filament/Resources/TournamentResource/`, `apps/web/app/Filament/Resources/GameMatchTypeResource/`
**Files read:** 25
**Pattern extraction date:** 2026-06-04
