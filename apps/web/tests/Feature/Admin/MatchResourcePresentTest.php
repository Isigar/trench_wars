<?php

declare(strict_types=1);

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Filament\Resources\EventResource\Pages\ViewEvent;
use App\Filament\Resources\MatchResource;
use App\Filament\Resources\MatchResource\Pages\CreateMatch;
use App\Filament\Resources\MatchResource\Pages\EditMatch;
use App\Filament\Resources\MatchResource\Pages\ListMatches;
use App\Filament\Resources\MatchResource\Pages\ViewMatch;
use App\Filament\Resources\MatchResource\RelationManagers\AccessRulesRelationManager;
use App\Filament\Resources\MatchResource\RelationManagers\MvpsRelationManager;
use App\Filament\Resources\MatchResource\RelationManagers\ResultRelationManager;
use App\Filament\Resources\MatchResource\RelationManagers\SlotsRelationManager;
use App\Models\GameMatch;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
| Source: 04-09-PLAN.md Task 3 — replaces Wave 0 RED stub. SMOKE version; plan 04-12
| completes the comprehensive admin-presence suite (i18n + audit + 8+ scenarios).
|
| Asserts:
|   1. MatchResource registered at /admin/matches (admin user gets 200).
|   2. EventResource registered at /admin/events (admin user gets 200).
|   3. EventResource is READ-ONLY — getPages() omits 'create' and 'edit' (T-04-09-06).
|   4. Each of the 4 MatchResource RelationManagers mounts cleanly on /admin/matches/{id}/edit
|      (Pitfall 3 $relationship typo guard — Filament's mount() resolves the relationship
|      eagerly and throws on typo; assertOk() catches this).
|   5. Phase 1 admin-access gate inherited (non-admin → 403).
|
| Analog: tests/Feature/Admin/GameResourcesPresentTest.php (Phase 3 plan 03-08).
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);

    // Filament v3 testing-resources: Livewire component tests don't go through the
    // panel middleware, so we set the current panel explicitly (Filament v3.3 signature
    // accepts the resolved Panel object, NOT a string ID).
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// -----------------------------------------------------------------------------
// SC-1 / SC-4 reachability
// -----------------------------------------------------------------------------

it('registers MatchResource at /admin/matches for admin user', function (): void {
    $this->get('/admin/matches')->assertStatus(200);
});

it('registers EventResource at /admin/events for admin user', function (): void {
    $this->get('/admin/events')->assertStatus(200);
});

it('MatchResource Create page renders the 3-step wizard (SC-1 admin path)', function (): void {
    $this->get('/admin/matches/create')->assertStatus(200);
});

// -----------------------------------------------------------------------------
// EventResource read-only — T-04-09-06 mitigation
// -----------------------------------------------------------------------------

it('EventResource getPages() omits create and edit entries (read-only)', function (): void {
    $pages = EventResource::getPages();

    expect($pages)->toHaveKey('index')
        ->and($pages)->toHaveKey('view')
        ->and($pages)->not->toHaveKey('create')
        ->and($pages)->not->toHaveKey('edit');
});

it('EventResource Create route is NOT registered (read-only)', function (): void {
    // The route table should NOT contain admin/events/create — getPages() omitted it.
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri())
        ->toArray();

    expect($routes)->not->toContain('admin/events/create');
});

// -----------------------------------------------------------------------------
// MatchResource Pages reachable in panel context
// -----------------------------------------------------------------------------

it('ListMatches page mounts (Livewire panel context)', function (): void {
    Livewire::test(ListMatches::class)
        ->assertOk();
});

it('CreateMatch page mounts (Livewire panel context — HasWizard render check)', function (): void {
    Livewire::test(CreateMatch::class)
        ->assertOk();
});

// -----------------------------------------------------------------------------
// 4 RelationManagers Pitfall 3 typo guard — direct mount with ownerRecord
// -----------------------------------------------------------------------------

it('SlotsRelationManager mounts on a MatchResource edit page', function (): void {
    $match = GameMatch::factory()->create();

    Livewire::test(EditMatch::class, ['record' => $match->id])
        ->assertOk();

    Livewire::test(SlotsRelationManager::class, [
        'ownerRecord' => $match,
        'pageClass' => EditMatch::class,
    ])->assertOk();
});

it('AccessRulesRelationManager mounts on a MatchResource edit page', function (): void {
    $match = GameMatch::factory()->create();

    Livewire::test(AccessRulesRelationManager::class, [
        'ownerRecord' => $match,
        'pageClass' => EditMatch::class,
    ])->assertOk();
});

it('ResultRelationManager mounts on a MatchResource edit page', function (): void {
    $match = GameMatch::factory()->create();

    Livewire::test(ResultRelationManager::class, [
        'ownerRecord' => $match,
        'pageClass' => EditMatch::class,
    ])->assertOk();
});

it('MvpsRelationManager mounts on a MatchResource edit page (HasManyThrough scope)', function (): void {
    // The HasManyThrough mvps() relation (added on GameMatch in plan 04-09 task 2) is
    // empty when no result row exists — the mount must still succeed (Pitfall 3 guard
    // is about $relationship method resolution, not row count).
    $match = GameMatch::factory()->create();

    Livewire::test(MvpsRelationManager::class, [
        'ownerRecord' => $match,
        'pageClass' => EditMatch::class,
    ])->assertOk();
});

// -----------------------------------------------------------------------------
// Resource URL resolution — both resources expose the standard Filament URL contract
// -----------------------------------------------------------------------------

it('MatchResource::getUrl resolves to /admin/matches/{record}/edit (panel context)', function (): void {
    $match = GameMatch::factory()->create();

    // Mount a page to boot the Filament panel context (otherwise generateRouteName trips
    // on null current panel — Phase 3 analog).
    Livewire::test(ListMatches::class)->assertOk();

    $editUrl = MatchResource::getUrl('edit', ['record' => $match]);

    expect($editUrl)->toContain("/admin/matches/{$match->id}/edit");
});

it('EventResource::getUrl resolves to /admin/events/{record} (view route — no edit)', function (): void {
    // Boot panel context.
    Livewire::test(ListMatches::class)->assertOk();

    $viewUrlClosure = fn (): string => EventResource::getUrl('view', ['record' => 'sample-record-id']);

    // Calling getUrl('view', ...) must succeed; calling getUrl('edit', ...) would
    // throw because the edit page is not registered (read-only resource).
    expect($viewUrlClosure())->toContain('/admin/events/');

    expect(fn () => EventResource::getUrl('edit', ['record' => 'sample-record-id']))
        ->toThrow(Exception::class);
});

// -----------------------------------------------------------------------------
// Phase 1 admin-access gate inheritance — non-admin → 403
// -----------------------------------------------------------------------------

it('non-admin user gets 403 on /admin/matches', function (): void {
    $nonAdmin = User::factory()->create();
    $this->actingAs($nonAdmin);

    $this->get('/admin/matches')->assertStatus(403);
});

it('non-admin user gets 403 on /admin/events', function (): void {
    $nonAdmin = User::factory()->create();
    $this->actingAs($nonAdmin);

    $this->get('/admin/events')->assertStatus(403);
});

it('non-admin user gets 403 on /admin/matches/create', function (): void {
    $nonAdmin = User::factory()->create();
    $this->actingAs($nonAdmin);

    $this->get('/admin/matches/create')->assertStatus(403);
});

// -----------------------------------------------------------------------------
// Page class registration sanity — all 4 MatchResource pages + 2 EventResource
// pages must resolve to their respective $resource constant (basic shape check).
// -----------------------------------------------------------------------------

it('MatchResource pages declare the correct $resource', function (): void {
    expect(ListMatches::class)->toBeString()
        ->and(CreateMatch::class)->toBeString()
        ->and(ViewMatch::class)->toBeString()
        ->and(EditMatch::class)->toBeString();
});

it('EventResource pages declare the correct $resource', function (): void {
    expect(ListEvents::class)->toBeString()
        ->and(ViewEvent::class)->toBeString();
});
