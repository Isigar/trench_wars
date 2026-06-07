<?php

declare(strict_types=1);

use App\Models\Clan;
use App\Models\ClanApplication;
use App\Models\ClanMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

/*
| Source: 02-11-PLAN.md Task 3 — replaces Wave 0 stub.
|
| Covers REQ-tenancy-multi-clan: ClanApplication state machine transitions
| pending -> accepted | declined | cancelled
|
| Trust boundaries tested (from threat model):
|   T-02-07-01 Accept only by Leader/Officer of target clan
|   T-02-07-02 Re-accept already-accepted application fails
|   T-02-07-03 Accept fails if applicant joined another clan in the meantime (atomicity)
|   T-02-07-04 Cross-clan listing prevention (implicit in route scoping)
*/

// ---------------------------------------------------------------------------
// Shared setup
// ---------------------------------------------------------------------------

/**
 * Create a clan with a leader, officer, and regular member.
 * Returns [$clan, $leader, $officer, $member, $leaderMembership, $officerMembership, $memberMembership].
 *
 * @return array{Clan, User, User, User, ClanMembership, ClanMembership, ClanMembership}
 */
function setupApplicationClan(): array
{
    $leader = User::factory()->create();
    $officer = User::factory()->create();
    $member = User::factory()->create();

    $clan = Clan::factory()->create(['owner_user_id' => $leader->id]);

    $leaderMembership = ClanMembership::factory()->create([
        'clan_id' => $clan->id,
        'user_id' => $leader->id,
        'role' => 'leader',
    ]);
    $officerMembership = ClanMembership::factory()->create([
        'clan_id' => $clan->id,
        'user_id' => $officer->id,
        'role' => 'officer',
    ]);
    $memberMembership = ClanMembership::factory()->create([
        'clan_id' => $clan->id,
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    return [$clan, $leader, $officer, $member, $leaderMembership, $officerMembership, $memberMembership];
}

// ===========================================================================
// POST /my-clan/applications/{application}/accept — Leader/Officer accepts
// ===========================================================================

it('Leader accepts pending application: status=accepted, ClanMembership created with role=recruit', function (): void {
    [$clan, $leader] = setupApplicationClan();
    $applicant = User::factory()->create();

    $app = ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    $this->actingAs($leader)
        ->post(route('my-clan.applications.accept', $app->id))
        ->assertRedirect();

    expect($app->fresh()->status)->toBe('accepted');
    expect($app->fresh()->decided_at)->not->toBeNull();
    expect($app->fresh()->decided_by)->toBe($leader->id);

    $membership = ClanMembership::where('user_id', $applicant->id)
        ->where('clan_id', $clan->id)
        ->whereNull('left_at')
        ->first();

    expect($membership)->not->toBeNull();
    expect($membership->role)->toBe('recruit');
    expect($membership->invited_by)->toBe($leader->id);
});

it('Officer accepts pending application: same as leader', function (): void {
    [$clan, $leader, $officer] = setupApplicationClan();
    $applicant = User::factory()->create();

    $app = ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    $this->actingAs($officer)
        ->post(route('my-clan.applications.accept', $app->id))
        ->assertRedirect();

    expect($app->fresh()->status)->toBe('accepted');

    $membership = ClanMembership::where('user_id', $applicant->id)
        ->where('clan_id', $clan->id)
        ->whereNull('left_at')
        ->first();

    expect($membership)->not->toBeNull();
    expect($membership->role)->toBe('recruit');
    expect($membership->invited_by)->toBe($officer->id);
});

it('Member cannot accept application (403)', function (): void {
    [$clan, $leader, $officer, $member] = setupApplicationClan();
    $applicant = User::factory()->create();

    $app = ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    $this->actingAs($member)
        ->post(route('my-clan.applications.accept', $app->id))
        ->assertStatus(403);

    expect($app->fresh()->status)->toBe('pending');
    expect(ClanMembership::where('user_id', $applicant->id)->count())->toBe(0);
});

it('Accept fails when applicant joined another clan in the meantime: 422 and transaction rolled back', function (): void {
    [$clan, $leader] = setupApplicationClan();
    $applicant = User::factory()->create();

    $app = ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    // Simulate race condition: applicant joined another clan before leader accepts.
    $otherClan = Clan::factory()->create();
    ClanMembership::factory()->create([
        'clan_id' => $otherClan->id,
        'user_id' => $applicant->id,
        'role' => 'member',
    ]);

    $this->actingAs($leader)
        ->post(route('my-clan.applications.accept', $app->id))
        ->assertSessionHasErrors(['application']);

    // Transaction must have rolled back — application still pending.
    expect($app->fresh()->status)->toBe('pending');

    // No membership created in the target clan.
    expect(ClanMembership::where('user_id', $applicant->id)->where('clan_id', $clan->id)->count())->toBe(0);
});

it('Accept fails for already-accepted application (422 not_pending)', function (): void {
    [$clan, $leader] = setupApplicationClan();
    $applicant = User::factory()->create();

    $app = ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'accepted',
        'decided_at' => now(),
        'decided_by' => $leader->id,
    ]);

    $this->actingAs($leader)
        ->post(route('my-clan.applications.accept', $app->id))
        ->assertSessionHasErrors(['application']);
});

// ===========================================================================
// POST /my-clan/applications/{application}/decline — Leader/Officer declines
// ===========================================================================

it('Leader declines pending application: status=declined, decided_by=leader, no membership created', function (): void {
    [$clan, $leader] = setupApplicationClan();
    $applicant = User::factory()->create();

    $app = ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    $this->actingAs($leader)
        ->post(route('my-clan.applications.decline', $app->id))
        ->assertRedirect();

    expect($app->fresh()->status)->toBe('declined');
    expect($app->fresh()->decided_at)->not->toBeNull();
    expect($app->fresh()->decided_by)->toBe($leader->id);
    expect(ClanMembership::where('user_id', $applicant->id)->count())->toBe(0);
});

it('Officer declines pending application', function (): void {
    [$clan, $leader, $officer] = setupApplicationClan();
    $applicant = User::factory()->create();

    $app = ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    $this->actingAs($officer)
        ->post(route('my-clan.applications.decline', $app->id))
        ->assertRedirect();

    expect($app->fresh()->status)->toBe('declined');
    expect($app->fresh()->decided_by)->toBe($officer->id);
});

it('Cross-clan Leader cannot accept or decline another clan\'s application (403)', function (): void {
    [$clan, $leader] = setupApplicationClan();

    // Another clan with its own pending application.
    $otherLeader = User::factory()->create();
    $otherClan = Clan::factory()->create(['owner_user_id' => $otherLeader->id]);
    ClanMembership::factory()->create([
        'clan_id' => $otherClan->id,
        'user_id' => $otherLeader->id,
        'role' => 'leader',
    ]);
    $otherApplicant = User::factory()->create();
    $otherApp = ClanApplication::factory()->create([
        'clan_id' => $otherClan->id,
        'applicant_user_id' => $otherApplicant->id,
        'status' => 'pending',
    ]);

    // $leader from first clan attempts to accept/decline the other clan's application.
    $this->actingAs($leader)
        ->post(route('my-clan.applications.accept', $otherApp->id))
        ->assertStatus(403);

    $this->actingAs($leader)
        ->post(route('my-clan.applications.decline', $otherApp->id))
        ->assertStatus(403);

    expect($otherApp->fresh()->status)->toBe('pending');
});

// ===========================================================================
// POST /applications/{application}/cancel — applicant cancels own application
// ===========================================================================

it('Applicant cancels own pending application: status=cancelled, decided_at set', function (): void {
    [$clan] = setupApplicationClan();
    $applicant = User::factory()->create();

    $app = ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    $this->actingAs($applicant)
        ->post(route('applications.cancel', $app->id))
        ->assertRedirect();

    expect($app->fresh()->status)->toBe('cancelled');
    expect($app->fresh()->decided_at)->not->toBeNull();
});

it('Non-applicant cannot cancel another user\'s application (403)', function (): void {
    [$clan] = setupApplicationClan();
    $applicant = User::factory()->create();
    $otherUser = User::factory()->create();

    $app = ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    $this->actingAs($otherUser)
        ->post(route('applications.cancel', $app->id))
        ->assertStatus(403);

    expect($app->fresh()->status)->toBe('pending');
});

// ===========================================================================
// Activity log — transitions are audited
// ===========================================================================

it('LogsActivity row is written when application is accepted', function (): void {
    [$clan, $leader] = setupApplicationClan();
    $applicant = User::factory()->create();

    $app = ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    $this->actingAs($leader)
        ->post(route('my-clan.applications.accept', $app->id));

    // Filter on the 'updated' event to avoid getting the 'created' activity
    // from the factory call (which has causer_id = null since no user was authed).
    $activity = Activity::where('subject_type', ClanApplication::class)
        ->where('subject_id', $app->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($leader->id);
});

it('LogsActivity row is written when application is declined', function (): void {
    [$clan, $leader] = setupApplicationClan();
    $applicant = User::factory()->create();

    $app = ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    $this->actingAs($leader)
        ->post(route('my-clan.applications.decline', $app->id));

    $activity = Activity::where('subject_type', ClanApplication::class)
        ->where('subject_id', $app->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($leader->id);
});

it('LogsActivity row is written when applicant cancels application', function (): void {
    [$clan] = setupApplicationClan();
    $applicant = User::factory()->create();

    $app = ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    $this->actingAs($applicant)
        ->post(route('applications.cancel', $app->id));

    $activity = Activity::where('subject_type', ClanApplication::class)
        ->where('subject_id', $app->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($applicant->id);
});

// ===========================================================================
// WR-01 — accept() flash message uses the APPLICANT's username, not the acceptor's
// ===========================================================================

it('accept() flash message contains the applicant username, not the acceptor username (WR-01)', function (): void {
    [$clan, $leader] = setupApplicationClan();
    $applicant = User::factory()->create(['username' => 'applicant-user-wr01']);

    $app = ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($leader)
        ->post(route('my-clan.applications.accept', $app->id))
        ->assertRedirect();

    // The success flash must contain the applicant's name, not the leader's.
    $response->assertSessionHas('success', fn (string $msg): bool => str_contains($msg, 'applicant-user-wr01'));
});

// ===========================================================================
// Applicant-facing surface on /my-clan — the only in-product entry point to
// withdraw an application you submitted (reachability-audit gap REACH-01).
// ===========================================================================

it('surfaces the applicant\'s own pending application on GET /my-clan with clan display fields', function (): void {
    [$clan] = setupApplicationClan();
    $applicant = User::factory()->create(); // no clan of their own

    ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
        'message' => 'Please let me in.',
    ]);

    $this->actingAs($applicant)
        ->get('/my-clan')
        ->assertStatus(200)
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('MyClan/Index', false)
                ->has('my_applications', 1)
                ->where('my_applications.0.clan_name', $clan->name)
                ->where('my_applications.0.clan_slug', $clan->slug)
                ->where('my_applications.0.message', 'Please let me in.')
        );
});

it('does not surface decided (accepted/declined/cancelled) applications in my_applications', function (): void {
    [$clan] = setupApplicationClan();
    $applicant = User::factory()->create();

    foreach (['accepted', 'declined', 'cancelled'] as $status) {
        ClanApplication::factory()->create([
            'clan_id' => $clan->id,
            'applicant_user_id' => $applicant->id,
            'status' => $status,
            'decided_at' => now(),
        ]);
    }

    $this->actingAs($applicant)
        ->get('/my-clan')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('my_applications', 0));
});

it('lets the applicant withdraw their application from the /my-clan surface', function (): void {
    [$clan] = setupApplicationClan();
    $applicant = User::factory()->create();

    $app = ClanApplication::factory()->create([
        'clan_id' => $clan->id,
        'applicant_user_id' => $applicant->id,
        'status' => 'pending',
    ]);

    // The surface posts to the same route the Withdraw button uses.
    $this->actingAs($applicant)
        ->post(route('applications.cancel', $app->id))
        ->assertRedirect();

    expect($app->fresh()->status)->toBe('cancelled');

    // Once withdrawn, it is no longer surfaced.
    $this->actingAs($applicant)
        ->get('/my-clan')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('my_applications', 0));
});
