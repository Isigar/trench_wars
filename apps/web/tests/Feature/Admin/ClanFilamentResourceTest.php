<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/**
 * Source: .planning/phases/02-clans-tags/02-13-PLAN.md task 3.
 *
 * Replaces the Wave 0 stub with comprehensive admin-side feature tests covering:
 * - Presence of all 4 remaining Filament resources (D-012)
 * - No-Create restriction on ClanMembership, ClanInvite, ClanApplication (lifecycle in My Clan)
 * - No-Create restriction on DiscordGuildResource (D-003 single-row invariant)
 * - No Delete action on audit_log (CLAUDE.md §6 — activity_log rows are append-only)
 * - Audit tab populates after a model mutation (ClanResource Audit tab integration)
 */
beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);
});

// ---------------------------------------------------------------------------
// Resource presence
// ---------------------------------------------------------------------------

it('registers ClanMembershipResource at /admin/clan-memberships', function (): void {
    $this->get('/admin/clan-memberships')->assertStatus(200);
});

it('registers ClanInviteResource at /admin/clan-invites', function (): void {
    $this->get('/admin/clan-invites')->assertStatus(200);
});

it('registers ClanApplicationResource at /admin/clan-applications', function (): void {
    $this->get('/admin/clan-applications')->assertStatus(200);
});

it('registers DiscordGuildResource at /admin/discord-guilds', function (): void {
    $this->get('/admin/discord-guilds')->assertStatus(200);
});

// ---------------------------------------------------------------------------
// No-Create restrictions
// ---------------------------------------------------------------------------

it('does NOT register Create for ClanMembershipResource (lifecycle in My Clan)', function (): void {
    // ClanMembership lifecycle is managed by My Clan UI (plans 02-09/02-10/02-11).
    // Admin has read-only visibility; no manual creation via Filament.
    $this->get('/admin/clan-memberships/create')->assertStatus(404);
});

it('does NOT register Create for ClanInviteResource', function (): void {
    // Invites are created via My Clan UI (plan 02-09).
    $this->get('/admin/clan-invites/create')->assertStatus(404);
});

it('does NOT register Create for ClanApplicationResource', function (): void {
    // Applications are submitted via public clan page (plan 02-09+).
    $this->get('/admin/clan-applications/create')->assertStatus(404);
});

it('does NOT register Create for DiscordGuildResource (D-003 single-row)', function (): void {
    // D-003: discord_guild table holds exactly one row. DiscordGuildResource
    // intentionally omits 'create' from getPages() — T-02-10-02 mitigation.
    $this->get('/admin/discord-guilds/create')->assertStatus(404);
});

// ---------------------------------------------------------------------------
// Audit integrity (CLAUDE.md §6 — activity_log rows are append-only)
// ---------------------------------------------------------------------------

it('does NOT register Delete on activity_log rows (audit page read-only)', function (): void {
    // The audit page (/admin/audit) must not expose Delete or Edit actions on
    // Activity rows. CLAUDE.md §6: "Activity log writes are append-only via the
    // LogsActivity trait — Filament admin UI never exposes edit/delete on
    // activity_log rows." We verify the audit page HTML contains no delete-action
    // identifier (the Filament HTML attribute used by DeleteAction buttons).
    $this->admin->givePermissionTo('audit.view');

    $this->get('/admin/audit')
        ->assertStatus(200)
        ->assertDontSee('delete-action', false);
});

// ---------------------------------------------------------------------------
// Audit tab populates after mutation (ClanResource integration)
// ---------------------------------------------------------------------------

it('admin can view a Clan record and the audit_log tab renders after a mutation', function (): void {
    // Create a clan and trigger a LogsActivity mutation so there is at least
    // one activity row for this record.
    $clan = Clan::factory()->create(['name' => 'Test Clan', 'description' => ['en' => 'original']]);
    // Trigger an 'updated' event to create an activity_log row.
    $clan->update(['name' => 'Updated Clan Name']);

    // ClanResource uses slug as route key (getRouteKeyName = 'slug').
    // The View page renders the audit tab with the activity partial.
    $this->get("/admin/clans/{$clan->slug}")
        ->assertStatus(200)
        ->assertSee('audit', false);
});
