<?php

declare(strict_types=1);

use App\Filament\Resources\GameMatchTypeResource;
use App\Filament\Resources\GameMatchTypeResource\Pages\EditGameMatchType;
use App\Filament\Resources\GameMatchTypeResource\RelationManagers\RoleLimitsRelationManager;
use App\Filament\Resources\GameResource\Pages\EditGame;
use App\Filament\Resources\GameResource\RelationManagers\MatchTypesRelationManager;
use App\Filament\Resources\GameResource\RelationManagers\RolesRelationManager;
use App\Models\Game;
use App\Models\GameMatchType;
use App\Models\GameRole;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * Source: .planning/phases/03-games-match-types/03-08-PLAN.md task 1.
 *
 * Replaces the Wave 0 RED stub (plan 03-01) with the canonical Phase 3 admin
 * presence + 403 gate test. Structural analog:
 *   apps/web/tests/Feature/Admin/ClanResourcesPresentTest.php
 *
 * Three security nets land here:
 *
 *  1. Pitfall 3 RelationManager $relationship typo guard (RESEARCH.md):
 *     A typo on `$relationship` silently mounts a blank tab. The HTTP-test
 *     fallback used by Phase 2 (assertSee on rendered child rows) does NOT
 *     work here because Filament v3 RelationManagers are `x-intersect`
 *     lazy-loaded — child rows only materialise after a Livewire fetch.
 *     Canonical Filament v3 test pattern (Filament docs `testing-resources`):
 *         Livewire::test(EditGame::class, ['record' => $game->id])
 *             ->assertSeeLivewire(RolesRelationManager::class);
 *     This mounts the Filament page through the panel context (resolving the
 *     `generateRouteName()` errors that plague raw `Resource::getUrl()` calls
 *     from outside the HTTP test kernel) and verifies the RelationManager
 *     Livewire child component is actually registered on the page. A typo on
 *     `$relationship` would still register the child (so this assertion alone
 *     is not perfect), so we ALSO load each RelationManager directly with the
 *     ownerRecord + pageClass props and call `->assertOk()` — Filament's
 *     mount() resolves the relationship eagerly and throws if the method name
 *     does not exist.
 *
 *  2. Pattern 2 click-through URL override (plan 03-07 task 3 amendment):
 *     The MatchTypesRelationManager EditAction is overridden to navigate to
 *     /admin/game-match-types/{record}/edit instead of opening a modal. We
 *     mount the RelationManager via Livewire (panel context booted) and call
 *     `GameMatchTypeResource::getUrl('edit', ['record' => $mt])` inside the
 *     panel — the result must match the expected /admin/game-match-types/{id}
 *     /edit pattern.
 *
 *  3. Phase 1 admin-access gate inheritance (D-012 + 01-12):
 *     Non-admin users get 403 on all admin/games/* and admin/game-match-types/*
 *     routes via canAccessPanel() → hasPermissionTo('admin-access', 'web').
 */
beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);

    // Filament v3 testing-resources docs: Livewire component tests don't go
    // through the panel middleware, so Filament::auth() / getCurrentPanel()
    // is null without this hint. The HTTP request tests above (this beforeEach
    // applies to all `it()` blocks) tolerate this no-op when the middleware
    // resets the current panel — see FilamentManager::bootCurrentPanel().
    //
    // Filament v3.3 signature: setCurrentPanel(?Panel $panel) — accepts the
    // resolved Panel object, NOT a string ID (string-arg form is v4-only).
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// -----------------------------------------------------------------------------
// SC-1 + SC-2 reachability
// -----------------------------------------------------------------------------

it('registers GameResource at /admin/games', function (): void {
    $this->get('/admin/games')->assertStatus(200);
});

it('registers GameMatchTypeResource at /admin/game-match-types', function (): void {
    $this->get('/admin/game-match-types')->assertStatus(200);
});

// -----------------------------------------------------------------------------
// Create-page form fields render (HTTP — no panel context needed for static form)
// -----------------------------------------------------------------------------

it('Filament create page for Game renders form with key + name fields', function (): void {
    $this->get('/admin/games/create')
        ->assertStatus(200)
        ->assertSee('key', false)
        ->assertSee('name', false);
});

it('Filament create page for GameMatchType requires game_id Select', function (): void {
    $this->get('/admin/game-match-types/create')
        ->assertStatus(200)
        // ->relationship('game', 'key') renders a Select whose state path is `game_id`
        // and whose label is 'Game' (admin.game_match_type.fields.game).
        ->assertSee('game_id', false)
        ->assertSee('Game', false);
});

// -----------------------------------------------------------------------------
// Edit page loads + Pitfall 2 KeyValue ['en' => ''] form-state verification
// (Livewire::test mounts the Filament page in panel context — canonical pattern)
// -----------------------------------------------------------------------------

it('Filament edit page for Game loads with name set from JSONB column (Pitfall 2 sanity)', function (): void {
    $game = Game::factory()->create(['name' => ['en' => 'pitfall2 game name']]);

    Livewire::test(EditGame::class, ['record' => $game->id])
        ->assertOk()
        // Form state hydrates the translatable JSONB as a 'en'-keyed array.
        // If Pitfall 2 coercion fails (null → KeyValue choke), the form would
        // not hydrate to this state.
        ->assertFormSet([
            'key' => $game->key,
            'name' => ['en' => 'pitfall2 game name'],
        ]);
});

it('Filament edit page for GameMatchType loads with name + description hydrated (Pitfall 2 two-field variant)', function (): void {
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create([
        'name' => ['en' => 'pitfall2 mt name'],
        'description' => ['en' => 'pitfall2 mt description'],
    ]);

    Livewire::test(EditGameMatchType::class, ['record' => $matchType->id])
        ->assertOk()
        ->assertFormSet([
            'key' => $matchType->key,
            'name' => ['en' => 'pitfall2 mt name'],
            'description' => ['en' => 'pitfall2 mt description'],
        ]);
});

// -----------------------------------------------------------------------------
// Pitfall 3 — RelationManager $relationship typo guard
// (canonical Filament v3 testing-resources pattern: livewire(EditPage)
//  ->assertSeeLivewire(RM) plus direct mount of the RM with ownerRecord)
// -----------------------------------------------------------------------------

it('Filament Game edit page mounts the RolesRelationManager (Pitfall 3 typo guard)', function (): void {
    $game = Game::factory()->create();

    GameRole::factory()->for($game)->create([
        'key' => 'pitfall3_role',
        'display_name' => ['en' => 'Pitfall 3 Role'],
    ]);

    // 1. assertSeeLivewire: verifies the RM is registered as a child component
    //    on the Filament EditGame page (canonical Filament docs pattern).
    Livewire::test(EditGame::class, ['record' => $game->id])
        ->assertOk()
        ->assertSeeLivewire(RolesRelationManager::class);

    // 2. Direct RM mount with ownerRecord + pageClass — Filament's mount()
    //    eagerly resolves $relationship via the ownerRecord's relationships
    //    list, so a typo here would throw. assertCanSeeTableRecords additionally
    //    proves the child row is actually fetched from `roles()` HasMany.
    Livewire::test(RolesRelationManager::class, [
        'ownerRecord' => $game,
        'pageClass' => EditGame::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords($game->roles);
});

it('Filament Game edit page mounts the MatchTypesRelationManager (Pitfall 3 typo guard)', function (): void {
    $game = Game::factory()->create();

    GameMatchType::factory()->for($game)->create([
        'key' => 'pitfall3_mt',
        'name' => ['en' => 'Pitfall 3 Match Type'],
    ]);

    // GameResource registers TWO RelationManagers (roles=0, matchTypes=1).
    // EditGame mounts only the active tab (default activeRelationManager='0'),
    // so assertSeeLivewire(MatchTypesRelationManager::class) on the freshly-
    // mounted page would fail because the Match Types tab is not yet active.
    // Switching the active tab forces Filament to mount the second child.
    Livewire::test(EditGame::class, ['record' => $game->id])
        ->assertOk()
        ->set('activeRelationManager', 1)
        ->assertSeeLivewire(MatchTypesRelationManager::class);

    // Direct RM mount — Filament eagerly resolves $relationship via the
    // ownerRecord's relationships list; a typo here would throw.
    Livewire::test(MatchTypesRelationManager::class, [
        'ownerRecord' => $game,
        'pageClass' => EditGame::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords($game->matchTypes);
});

it('Filament GameMatchType edit page mounts the RoleLimitsRelationManager (Pitfall 3 typo guard)', function (): void {
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create();

    $matchType->roleLimits()->create([
        'game_role_id' => $role->id,
        'capacity' => 42,
        'sort_order' => 0,
    ]);

    Livewire::test(EditGameMatchType::class, ['record' => $matchType->id])
        ->assertOk()
        ->assertSeeLivewire(RoleLimitsRelationManager::class);

    Livewire::test(RoleLimitsRelationManager::class, [
        'ownerRecord' => $matchType,
        'pageClass' => EditGameMatchType::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords($matchType->roleLimits);
});

// -----------------------------------------------------------------------------
// Pattern 2 click-through — MatchTypesRelationManager EditAction URL override
// (plan 03-07 task 3 Rule-2 amendment verification)
// -----------------------------------------------------------------------------

it('GameMatchTypeResource::getUrl resolves to the standalone edit page (Pattern 2 click-through target)', function (): void {
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create(['key' => 'pattern2_mt']);

    // Mount the Game edit page to boot the Filament panel context — outside this
    // context, Resource::getUrl() trips "Call to a member function generateRouteName() on null".
    Livewire::test(EditGame::class, ['record' => $game->id])->assertOk();

    $resolvedUrl = GameMatchTypeResource::getUrl('edit', ['record' => $matchType]);

    expect($resolvedUrl)->toContain("/admin/game-match-types/{$matchType->id}/edit");
});

// -----------------------------------------------------------------------------
// Phase 1 admin-access gate inheritance — non-admin → 403
// -----------------------------------------------------------------------------

it('non-admin user gets 403 on /admin/games', function (): void {
    $nonAdmin = User::factory()->create();
    $this->actingAs($nonAdmin);

    $this->get('/admin/games')->assertStatus(403);
});

it('non-admin user gets 403 on /admin/games/create', function (): void {
    $nonAdmin = User::factory()->create();
    $this->actingAs($nonAdmin);

    $this->get('/admin/games/create')->assertStatus(403);
});

it('non-admin user gets 403 on /admin/game-match-types', function (): void {
    $nonAdmin = User::factory()->create();
    $this->actingAs($nonAdmin);

    $this->get('/admin/game-match-types')->assertStatus(403);
});

it('non-admin user gets 403 on /admin/game-match-types/create', function (): void {
    $nonAdmin = User::factory()->create();
    $this->actingAs($nonAdmin);

    $this->get('/admin/game-match-types/create')->assertStatus(403);
});
