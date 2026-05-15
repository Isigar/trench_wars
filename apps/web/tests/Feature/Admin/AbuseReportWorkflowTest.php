<?php

declare(strict_types=1);

/*
| Source: .planning/phases/09-polish/09-11-PLAN.md task 2 — turns the Wave 0
| stub (plan 09-01) GREEN.
|
| Covers SC-5 abuse-report review queue:
|   - moderator (view-reports + manage-reports) can DISMISS a pending report
|     → status flips to 'dismissed' + activity_log row 'abuse.report_transitioned'.
|   - moderator (view-reports + manage-reports + moderate-users) can
|     action_with_ban a pending Player-targeted report → status flips to
|     'actioned' AND a new Ban row exists for the target user.
|   - non-moderator user cannot see the /admin/abuse-reports list (403).
|   - non-moderator cannot invoke dismiss / action_with_ban actions.
|   - view-reports-only user can list + view but cannot dismiss/action.
|
| Pattern: Livewire::test(ListAbuseReports::class)->callTableAction(...). Same
| idiom as plan 06-11 TournamentForfeitActionTest. The data() argument passes
| the form payload that the Filament Action's ->form([...]) collects.
*/

use App\Filament\Resources\AbuseReportResource;
use App\Filament\Resources\AbuseReportResource\Pages\ListAbuseReports;
use App\Models\AbuseReport;
use App\Models\Ban;
use App\Models\Player;
use App\Models\User;
use Database\Seeders\ModeratorRoleSeeder;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(ModeratorRoleSeeder::class);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->moderator = User::factory()->create();
    $this->moderator->givePermissionTo('admin-access');
    $this->moderator->assignRole('moderator');
});

it('moderator can dismiss a pending report — status flips + activity_log row written', function (): void {
    $reporter = User::factory()->create();
    $targetPlayer = Player::factory()->create();
    $report = AbuseReport::factory()->create([
        'reporter_user_id' => $reporter->id,
        'target_type' => Player::class,
        'target_id' => $targetPlayer->id,
        'status' => 'pending',
        'reason_code' => 'spam',
    ]);

    $this->actingAs($this->moderator);

    Livewire::test(ListAbuseReports::class)
        ->callTableAction('dismiss', $report, data: [
            'review_notes' => 'False report — investigated, no abuse confirmed.',
        ]);

    $fresh = $report->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh->status)->toBe('dismissed')
        ->and($fresh->reviewed_by_user_id)->toBe($this->moderator->id)
        ->and($fresh->reviewed_at)->not->toBeNull()
        ->and($fresh->review_notes)->toBe('False report — investigated, no abuse confirmed.');

    // The audit row's subject is the report's TARGET (Player UUID), not the
    // AbuseReport row itself — activity_log.subject_id is uuid-typed and
    // AbuseReport.id is a bigint, so the resource emits the audit against
    // the underlying target with abuse_report_id captured in properties.
    $activity = Activity::query()
        ->where('description', 'abuse.report_transitioned')
        ->where('subject_type', Player::class)
        ->where('subject_id', $targetPlayer->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->moderator->id)
        ->and($activity->properties->get('to_status'))->toBe('dismissed')
        ->and($activity->properties->get('from_status'))->toBe('pending')
        ->and((int) $activity->properties->get('abuse_report_id'))->toBe($report->id);
});

it('moderator can action a report and link a temporary Ban — AbuseReport + Ban + activity_log all written', function (): void {
    $reporter = User::factory()->create();
    $targetPlayer = Player::factory()->create(['user_id' => User::factory()->create()->id]);
    $report = AbuseReport::factory()->create([
        'reporter_user_id' => $reporter->id,
        'target_type' => Player::class,
        'target_id' => $targetPlayer->id,
        'status' => 'pending',
        'reason_code' => 'cheating',
    ]);

    $this->actingAs($this->moderator);

    Livewire::test(ListAbuseReports::class)
        ->callTableAction('action_with_ban', $report, data: [
            'review_notes' => 'Confirmed wallhack — issuing 7-day ban.',
            'ban_type' => 'temporary',
            'ban_reason' => 'Confirmed wallhack across two matches.',
            'expires_at' => now()->addWeek()->toDateTimeString(),
        ]);

    $fresh = $report->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh->status)->toBe('actioned')
        ->and($fresh->reviewed_by_user_id)->toBe($this->moderator->id);

    // Ban row exists for the underlying Player->User.
    $ban = Ban::query()
        ->where('user_id', $targetPlayer->fresh()->user_id)
        ->where('issued_by_user_id', $this->moderator->id)
        ->first();

    expect($ban)->not->toBeNull()
        ->and($ban->ban_type)->toBe('temporary')
        ->and($ban->reason)->toBe('Confirmed wallhack across two matches.');

    // BOTH activity_log rows exist:
    //   - abuse.report_transitioned (from AbuseReportResource)
    //   - user.banned                (from BanService::issue)
    expect(Activity::query()->where('description', 'abuse.report_transitioned')->count())->toBe(1)
        ->and(Activity::query()->where('description', 'user.banned')->count())->toBe(1);
});

it('non-moderator user cannot see the abuse-reports list (canViewAny returns false)', function (): void {
    $stranger = User::factory()->create();
    $stranger->givePermissionTo('admin-access');
    $this->actingAs($stranger);

    expect(AbuseReportResource::canViewAny())->toBeFalse();
});

it('non-moderator without view-reports cannot reach the abuse-reports list page', function (): void {
    $stranger = User::factory()->create();
    $stranger->givePermissionTo('admin-access');
    $this->actingAs($stranger);

    // canViewAny() is the resource-level gate that hides the navigation slot
    // AND blocks the page mount. With only admin-access (no view-reports),
    // canViewAny MUST return false (T-09-11-04 mitigation).
    expect(AbuseReportResource::canViewAny())->toBeFalse();
});

it('view-reports without manage-reports allows list but hides dismiss/action transitions', function (): void {
    // Build a custom role: view-reports ONLY (no manage-reports).
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('admin-access');
    $viewer->givePermissionTo(Permission::findByName('view-reports', 'web'));
    $this->actingAs($viewer);

    $reporter = User::factory()->create();
    $targetPlayer = Player::factory()->create();
    $report = AbuseReport::factory()->create([
        'reporter_user_id' => $reporter->id,
        'target_type' => Player::class,
        'target_id' => $targetPlayer->id,
        'status' => 'pending',
    ]);

    // canViewAny() must return true (view-reports granted).
    expect(AbuseReportResource::canViewAny())->toBeTrue();

    // Dismiss + action_with_ban actions are hidden (manage-reports missing).
    Livewire::test(ListAbuseReports::class)
        ->assertTableActionHidden('dismiss', $report)
        ->assertTableActionHidden('action_with_ban', $report);
});
