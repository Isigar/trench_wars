<?php

declare(strict_types=1);

use App\Filament\Resources\TournamentResource\Pages\EditTournament;
use App\Models\Clan;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameRole;
use App\Models\MatchResult;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| Source: 06-11-PLAN.md Task 2 — replaces Wave 0 RED stub from 06-01.
|
| Covers SC-5 reseed admin action:
|   - Visible only when Tournament::canReseed() returns true
|     (status='seeded' AND no MatchResult exists for any bracket-linked match)
|   - Hidden once a MatchResult lands (Open Question A4 LOCKED — plan 06-05
|     T-06-05-01 guard)
|   - On success: status temporarily flips seeded→registering→seeded with new
|     1..N seeds, an activity_log row captures previous_seeds + new_seeds
|
| Pattern: callAction('reseed', ['strategy' => ...]) — TournamentSeedingService::
| reseed() throws SeedingNotAllowedException when canReseed() fails, so the
| once-the-result-lands case is asserted by ->assertActionHidden('reseed') (the
| Filament Action::visible() guard prevents the action from being callable —
| the service-layer exception is the second defence in plan 06-05's own test).
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/**
 * Build a tournament in status='seeded' with $count active 1..N-seeded
 * participants — the canReseed precondition holds (no MatchResult yet).
 */
function makeSeededTournament(int $count = 4): Tournament
{
    $tournament = Tournament::factory()->inStatus('seeded')->create();
    $clans = Clan::factory()->count($count)->create();
    foreach ($clans as $i => $clan) {
        TournamentParticipant::factory()
            ->for($tournament)
            ->for($clan)
            ->create([
                'status' => 'active',
                'seed' => $i + 1,
            ]);
    }

    return $tournament;
}

// -----------------------------------------------------------------------------
// Happy path — reseed allowed when no MatchResult exists
// -----------------------------------------------------------------------------

it('reseed action succeeds when canReseed returns true (no match results)', function (): void {
    $tournament = makeSeededTournament(4);

    expect($tournament->canReseed())->toBeTrue();

    Livewire::test(EditTournament::class, ['record' => $tournament->getRouteKey()])
        ->callAction('reseed', ['strategy' => 'random']);

    // Status remains 'seeded' after the back-transition + forward transition.
    expect($tournament->fresh()->status)->toBe('seeded');

    // Seeds are re-assigned 1..N.
    $seeds = $tournament->fresh()->participants->pluck('seed')->sort()->values()->all();
    expect($seeds)->toBe([1, 2, 3, 4]);
});

it('reseed action writes activity_log row with previous_seeds + new_seeds', function (): void {
    $tournament = makeSeededTournament(4);

    Livewire::test(EditTournament::class, ['record' => $tournament->getRouteKey()])
        ->callAction('reseed', ['strategy' => 'by_rank']);

    $log = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('description', 'Tournament reseeded')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties->get('strategy'))->toBe('by_rank');
    expect($log->properties->get('previous_seeds'))->toBeArray();
    expect($log->properties->get('new_seeds'))->toBeArray();
});

// -----------------------------------------------------------------------------
// Negative — reseed hidden when a MatchResult exists (Open Question A4 LOCKED)
// -----------------------------------------------------------------------------

it('reseed action is hidden once a MatchResult has been recorded (canReseed=false)', function (): void {
    // Build a full SC-5 chain so canReseed sees a MatchResult through the
    // bracket → match_id → match_results subquery.
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    GameRole::factory()->for($game)->create();

    $tournament = Tournament::factory()
        ->for($game)
        ->inStatus('seeded')
        ->create(['default_game_match_type_id' => $matchType->id]);

    $stage = TournamentStage::factory()->for($tournament)->create([
        'type' => 'elim',
        'ordinal' => 1,
    ]);

    $clanA = Clan::factory()->create();
    $clanB = Clan::factory()->create();
    $pA = TournamentParticipant::factory()->for($tournament)->for($clanA)->create(['status' => 'active', 'seed' => 1]);
    $pB = TournamentParticipant::factory()->for($tournament)->for($clanB)->create(['status' => 'active', 'seed' => 2]);

    $match = GameMatch::factory()->for($matchType)->create();
    TournamentBracket::factory()->create([
        'tournament_stage_id' => $stage->id,
        'round_number' => 1,
        'position' => 1,
        'participant_a_id' => $pA->id,
        'participant_b_id' => $pB->id,
        'match_id' => $match->id,
    ]);

    MatchResult::factory()->create(['match_id' => $match->id, 'winner_clan_id' => $clanA->id]);

    // canReseed must now be false (a MatchResult exists for a bracket-linked match).
    expect($tournament->fresh()->canReseed())->toBeFalse();

    Livewire::test(EditTournament::class, ['record' => $tournament->getRouteKey()])
        ->assertActionHidden('reseed');
});

it('reseed action is hidden when status is not seeded (e.g. draft)', function (): void {
    $tournament = Tournament::factory()->inStatus('draft')->create();

    Livewire::test(EditTournament::class, ['record' => $tournament->getRouteKey()])
        ->assertActionHidden('reseed');
});
