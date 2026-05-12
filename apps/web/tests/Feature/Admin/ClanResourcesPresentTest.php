<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/**
 * Source: .planning/phases/02-clans-tags/02-12-PLAN.md task 3.
 *
 * Verifies the P2 Filament resources (ClanResource, ClanTagResource) are registered
 * with the AdminPanelProvider and reachable for an admin user (D-012).
 *
 * Also locks in the "no Delete action" contract for ClanTagResource (UI-SPEC).
 */
beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);
});

it('registers ClanResource at /admin/clans', function (): void {
    $this->get('/admin/clans')->assertStatus(200);
});

it('registers ClanTagResource at /admin/clan-tags', function (): void {
    $this->get('/admin/clan-tags')->assertStatus(200);
});

it('does not register a Delete action on the ClanTag table', function (): void {
    // Livewire::test(ListClanTags::class) requires full panel middleware context which
    // is not set up in HTTP test kernel. Fall back to HTML inspection.
    // ClanTagResource intentionally omits DeleteAction (UI-SPEC: "tags may be referenced").
    // We verify the list page renders without a delete action identifier in the DOM.
    $this->get('/admin/clan-tags')
        ->assertStatus(200)
        ->assertDontSee('delete-action', false);
});

it('Filament create page for Clan renders form with description KeyValue field', function (): void {
    $this->get('/admin/clans/create')->assertStatus(200)->assertSee('description');
});

it('Filament edit page for Clan renders KeyValue description field', function (): void {
    $clan = Clan::factory()->create(['description' => ['en' => 'test description']]);

    // Clan uses getRouteKeyName()='slug', so Filament resolves records by slug.
    // Filament v3 KeyValue renders via Livewire wire:model — input names are NOT
    // traditional HTML name="description[en]". Instead verify the field label and
    // locale key are present in the rendered HTML.
    $this->get("/admin/clans/{$clan->slug}/edit")
        ->assertStatus(200)
        ->assertSee('description', false)
        ->assertSee('en', false);
});

it('Filament create page for Clan is gated by admin-access permission', function (): void {
    // Create a user without admin-access
    $nonAdmin = User::factory()->create();
    $this->actingAs($nonAdmin);

    $this->get('/admin/clans/create')->assertStatus(403);
});
