<?php

declare(strict_types=1);

use App\Filament\Resources\MatchResource\Pages\CreateMatch;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Models\User;
use App\Services\MatchSlotMaterialiserService;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
| Source: 04-09-PLAN.md Task 3 — replaces Wave 0 RED stub.
|
| Covers SC-1 (officer/admin creates Match via Filament 3-step wizard → slots
| materialised → signups open immediately) and the Pitfall 3 transactional rollback
| invariant (T-04-09-02 mitigation: handleRecordCreation wraps GameMatch::create +
| MatchSlotMaterialiserService::materialise in a SINGLE DB::transaction).
|
| Pattern: Filament v3 Livewire test helper (panels/testing-resources) —
|   Livewire::test(CreateMatch::class)->fillForm([...])->call('create');
|
| The wizard step layout (Type, Schedule, Review) does NOT prevent fillForm() from
| filling all fields at once and calling create — Filament's HasWizard merges all
| step schemas into a single form state on submission.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ---------------------------------------------------------------------------
// SC-1: admin creates match via wizard → status='open' + slots materialised
// ---------------------------------------------------------------------------

it('admin can create a match via the 3-step wizard with status=open and N slots materialised', function (): void {
    // Build a GameMatchType with a 3-role capacity matrix (capacities [2,3,1] = 6 slots).
    $matchType = GameMatchType::factory()->create();
    /** @var GameMatchType $matchType */
    $game = $matchType->game;
    foreach ([2, 3, 1] as $i => $capacity) {
        $role = GameRole::factory()->for($game)->create(['sort_order' => $i]);
        GameMatchTypeRoleLimit::factory()->create([
            'game_match_type_id' => $matchType->id,
            'game_role_id' => $role->id,
            'capacity' => $capacity,
            'sort_order' => $i,
        ]);
    }

    $organiser = User::factory()->create();
    $matchesBefore = GameMatch::count();
    $slotsBefore = MatchSlot::count();

    Livewire::test(CreateMatch::class)
        ->fillForm([
            'game_match_type_id' => $matchType->id,
            'scheduled_at' => now()->addDays(7)->toDateTimeString(),
            'organiser_user_id' => $organiser->id,
            'host_clan_id' => null,
            'server_address' => 'hll.example.test:7777',
            'is_public' => true,
            'title' => ['en' => 'Wizard-created match'],
            'description' => ['en' => 'Created via Filament wizard test'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // SC-1 invariant 1: exactly one new Match row landed.
    expect(GameMatch::count())->toBe($matchesBefore + 1);

    $match = GameMatch::latest('created_at')->first();
    expect($match)->not->toBeNull();

    // SC-1 invariant 2: status defaults to 'open' (signups open immediately).
    expect($match->status)->toBe('open');

    // SC-1 invariant 3: 6 slots materialised (capacity matrix sum).
    expect(MatchSlot::where('match_id', $match->id)->count())->toBe(6);
    expect(MatchSlot::count())->toBe($slotsBefore + 6);

    // SC-1 invariant 4: organiser + matchType wired correctly.
    expect($match->organiser_user_id)->toBe($organiser->id);
    expect($match->game_match_type_id)->toBe($matchType->id);
});

// ---------------------------------------------------------------------------
// Pitfall 3 proof: materialiser failure rolls back the parent Match row
// (T-04-09-02 mitigation — orphan Match with zero slots is impossible)
// ---------------------------------------------------------------------------

it('rolls back Match + slots when MatchSlotMaterialiserService throws (Pitfall 3 / T-04-09-02)', function (): void {
    $matchType = GameMatchType::factory()->create();
    /** @var GameMatchType $matchType */
    $game = $matchType->game;
    $role = GameRole::factory()->for($game)->create();
    GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 4,
        'sort_order' => 0,
    ]);
    $organiser = User::factory()->create();

    // Replace the materialiser binding in the container with a closure that throws.
    // `final` on MatchSlotMaterialiserService blocks both Mockery and anonymous-class
    // extension; container bind() bypasses both because the `materialise` method is
    // resolved dynamically on the bound instance — the bound object only needs to
    // expose a callable `materialise` of the right shape. We use a stdClass-style
    // anonymous class that mimics the public surface without extending.
    //
    // The CreateMatch handleRecordCreation resolves the service via
    // app(MatchSlotMaterialiserService::class). When the container has a custom
    // binding for that key, Laravel honours it regardless of the FQN-vs-instance
    // type relationship — the call site `->materialise($match)` then dispatches to
    // our stub. PHP doesn't enforce parameter type covariance at the call site
    // because the source code's resolved variable type from app(...) is the
    // requested FQN, but at runtime PHP uses dynamic dispatch.
    $this->app->bind(MatchSlotMaterialiserService::class, function () {
        return new class
        {
            public function materialise(GameMatch $match): int
            {
                throw new RuntimeException('simulated materialiser failure');
            }
        };
    });

    $matchesBefore = GameMatch::count();
    $slotsBefore = MatchSlot::count();

    // The throw propagates out of handleRecordCreation; Livewire surfaces it.
    try {
        Livewire::test(CreateMatch::class)
            ->fillForm([
                'game_match_type_id' => $matchType->id,
                'scheduled_at' => now()->addDays(7)->toDateTimeString(),
                'organiser_user_id' => $organiser->id,
                'host_clan_id' => null,
                'server_address' => null,
                'is_public' => true,
                'title' => ['en' => 'Should roll back'],
                'description' => ['en' => ''],
            ])
            ->call('create');

        $this->fail('Expected RuntimeException to propagate from CreateMatch::create()');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('simulated materialiser failure');
    }

    // T-04-09-02 mitigation proof: zero orphan Match rows landed.
    expect(GameMatch::count())->toBe($matchesBefore);
    expect(MatchSlot::count())->toBe($slotsBefore);
});

// ---------------------------------------------------------------------------
// Phase 1 admin-access gate inheritance
// ---------------------------------------------------------------------------

it('non-admin user cannot reach the wizard create page (403)', function (): void {
    $nonAdmin = User::factory()->create();
    $this->actingAs($nonAdmin);

    $this->get('/admin/matches/create')->assertStatus(403);
});
