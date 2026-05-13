<?php

declare(strict_types=1);

/*
| Source: 06-12-PLAN.md Task 2 — SC-1 capstone, replaces Wave 0 RED stub.
|
| End-to-end 8-clan single-elimination tournament happy path:
|   1. Admin creates Tournament (draft → registering).
|   2. Eight clans register as TournamentParticipant rows.
|   3. TournamentSeedingService::seed('by_rank') assigns seeds 1..8.
|   4. Tournament transitions registering → seeded.
|   5. BracketGeneratorService::generate creates 7 brackets (4 + 2 + 1).
|   6. Tournament transitions seeded → running.
|   7. BracketMatchMaterialiserService materialises round-1 GameMatches.
|   8. Walk all 7 brackets: record MatchResult per materialised bracket (top
|      seed wins each time). MatchResultObserver fires advance() which
|      propagates winners + materialises the next bracket lazily.
|   9. Once all brackets are decided, BracketAdvancementService auto-transitions
|      running → completed.
|  10. Assert: status='completed' + top-seed participant has rank 1.
|  11. Public surface assertions: GET /tournaments/{slug} → 200; GET
|      /tournaments/{slug}.json → 200 with all 7 brackets having
|      winner_participant_id set.
|
| Coverage:
|   - SC-1 (8-clan single-elim end-to-end through observer chain)
|   - SC-3 second half (public Show page + JSON polling endpoint render)
|   - Services 06-04 (status) + 06-05 (seeding) + 06-06 (generator + materialiser)
|     + 06-08 (advancement observer) + 06-09 (standings) wired correctly.
|
| NAMING (D-04-03-A): GameMatch is the Phase 4 match model.
*/

use App\Models\Clan;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameMatchTypeRoleLimit;
use App\Models\GameRole;
use App\Models\MatchResult;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\User;
use App\Services\BracketMatchMaterialiserService;
use App\Services\Brackets\BracketGeneratorService;
use App\Services\TournamentSeedingService;
use App\Services\TournamentStatusService;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * Build the GameMatchType + RoleLimit so the materialiser can spawn 2 slots
 * per bracket-match (one per side). One role with capacity=2 is enough.
 */
function makeCapstoneMatchType(Game $game): GameMatchType
{
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create();
    GameMatchTypeRoleLimit::factory()->create([
        'game_match_type_id' => $matchType->id,
        'game_role_id' => $role->id,
        'capacity' => 2,
    ]);

    return $matchType;
}

/**
 * Decide every undecided + materialised bracket in $tournament until none remain.
 * Top-seed always wins. After each result, materialise any newly-populated
 * downstream brackets so the observer can drive the tree forward.
 */
function walkCapstone(Tournament $tournament): void
{
    $materialiser = app(BracketMatchMaterialiserService::class);
    $stageIds = $tournament->stages()->pluck('id');

    // Cap the outer loop at 32 iterations — a safety brake. An 8-clan single-elim
    // has at most 7 brackets so 32 ticks is well over the worst case.
    $safety = 0;
    while ($safety < 32) {
        $safety++;

        // Pick the next undecided + materialised bracket with both participants known.
        /** @var TournamentBracket|null $bracket */
        $bracket = TournamentBracket::query()
            ->whereIn('tournament_stage_id', $stageIds)
            ->whereNotNull('match_id')
            ->whereNull('winner_participant_id')
            ->whereNotNull('participant_a_id')
            ->whereNotNull('participant_b_id')
            ->orderBy('round_number')
            ->orderBy('position')
            ->first();

        if ($bracket === null) {
            // No materialised undecided bracket. If a downstream bracket has both
            // participants but is NOT yet materialised, materialise it now and
            // continue the loop. Otherwise we're done.
            /** @var TournamentBracket|null $unmaterialised */
            $unmaterialised = TournamentBracket::query()
                ->whereIn('tournament_stage_id', $stageIds)
                ->whereNull('match_id')
                ->whereNotNull('participant_a_id')
                ->whereNotNull('participant_b_id')
                ->orderBy('round_number')
                ->orderBy('position')
                ->first();

            if ($unmaterialised === null) {
                break; // tree fully decided
            }

            $materialiser->materialiseFor($unmaterialised, $tournament);

            continue;
        }

        // Top seed wins. participant_a is the higher seed (inner_outer pairing).
        /** @var TournamentParticipant $pA */
        $pA = $bracket->participantA()->firstOrFail();
        /** @var TournamentParticipant $pB */
        $pB = $bracket->participantB()->firstOrFail();
        $winnerClanId = ($pA->seed <= $pB->seed) ? $pA->clan_id : $pB->clan_id;

        /** @var GameMatch $match */
        $match = $bracket->match()->firstOrFail();

        // MatchResultObserver::created fires advance() which walks the chain.
        MatchResult::factory()->for($match, 'match')->create([
            'winner_clan_id' => $winnerClanId,
        ]);
    }
}

// ---------------------------------------------------------------------------
// SC-1 capstone — 8-clan single-elim end-to-end
// ---------------------------------------------------------------------------

it('SC-1 capstone: 8-clan single-elim end-to-end through the observer chain', function (): void {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $game = Game::factory()->create();
    $matchType = makeCapstoneMatchType($game);

    // 1. Admin creates the Tournament + transitions to registering.
    $tournament = Tournament::factory()
        ->ofFormat('single_elimination')
        ->for($game)
        ->create([
            'organiser_user_id' => $admin->id,
            'default_game_match_type_id' => $matchType->id,
            'status' => 'draft',
            'is_public' => true,
            'slug' => 'capstone-open',
            'starts_at' => now()->addDays(7),
        ]);

    app(TournamentStatusService::class)->transition($tournament, 'registering');

    // 2. Eight clans register.
    $clans = Clan::factory()->count(8)->create();
    TournamentParticipant::factory()
        ->for($tournament)
        ->count(8)
        ->state(new Sequence(...array_map(
            fn (int $i): array => [
                'clan_id' => $clans[$i]->id,
                'status' => 'registered',
            ],
            range(0, 7)
        )))
        ->create();

    expect($tournament->participants()->count())->toBe(8);

    // 3. Seed by_rank → flips participants registered → active and seeds 1..8.
    app(TournamentSeedingService::class)->seed($tournament, 'by_rank');
    app(TournamentStatusService::class)->transition($tournament->refresh(), 'seeded');

    expect($tournament->refresh()->status)->toBe('seeded');
    expect($tournament->participants()->whereNotNull('seed')->count())->toBe(8);

    // 4. Generate brackets — 7 total (4 + 2 + 1) under a single stage.
    app(BracketGeneratorService::class)->generate($tournament);

    expect($tournament->refresh()->stages()->count())->toBe(1);
    $stage = $tournament->stages()->first();
    expect($stage)->not->toBeNull();
    expect($stage->brackets()->count())->toBe(7);

    // 5. Start the tournament + materialise round-1 GameMatches.
    app(TournamentStatusService::class)->transition($tournament->refresh(), 'running');
    expect($tournament->refresh()->status)->toBe('running');

    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);

    // 6. Walk every bracket. Observer drives downstream advancement.
    walkCapstone($tournament);

    // 7. Final state — tournament auto-completed.
    $tournament->refresh();
    expect($tournament->status)->toBe('completed');

    $allBrackets = TournamentBracket::query()
        ->whereIn('tournament_stage_id', $tournament->stages()->pluck('id'))
        ->get();
    expect($allBrackets->count())->toBe(7);
    foreach ($allBrackets as $b) {
        expect($b->winner_participant_id)->not->toBeNull();
    }

    // 8. The top seed (seed=1) won every match → its participant should be the
    //    overall winner. Locate the final bracket (round 3, position 1).
    $final = TournamentBracket::query()
        ->whereIn('tournament_stage_id', $tournament->stages()->pluck('id'))
        ->where('round_number', 3)
        ->where('position', 1)
        ->firstOrFail();

    $winnerParticipant = $final->winnerParticipant()->firstOrFail();
    expect($winnerParticipant->seed)->toBe(1);
});

// ---------------------------------------------------------------------------
// Public surface — Show + JSON endpoint render the completed tournament
// ---------------------------------------------------------------------------

it('SC-1 capstone (cont.): public Show + JSON endpoint render the completed tournament', function (): void {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $game = Game::factory()->create();
    $matchType = makeCapstoneMatchType($game);

    $tournament = Tournament::factory()
        ->ofFormat('single_elimination')
        ->for($game)
        ->create([
            'organiser_user_id' => $admin->id,
            'default_game_match_type_id' => $matchType->id,
            'status' => 'draft',
            'is_public' => true,
            'slug' => 'capstone-public',
            'starts_at' => now()->addDays(7),
        ]);

    app(TournamentStatusService::class)->transition($tournament, 'registering');

    $clans = Clan::factory()->count(8)->create();
    TournamentParticipant::factory()
        ->for($tournament)
        ->count(8)
        ->state(new Sequence(...array_map(
            fn (int $i): array => [
                'clan_id' => $clans[$i]->id,
                'status' => 'registered',
            ],
            range(0, 7)
        )))
        ->create();

    app(TournamentSeedingService::class)->seed($tournament, 'by_rank');
    app(TournamentStatusService::class)->transition($tournament->refresh(), 'seeded');
    app(BracketGeneratorService::class)->generate($tournament);
    app(TournamentStatusService::class)->transition($tournament->refresh(), 'running');
    app(BracketMatchMaterialiserService::class)->materialiseFirstRound($tournament);
    walkCapstone($tournament);

    // Guest visitor (sign out the actingAs admin) sees the public Show page.
    auth()->logout();

    $this->get(route('tournaments.show', $tournament))
        ->assertOk();

    $jsonResponse = $this->get(route('tournaments.show.json', $tournament));
    $jsonResponse->assertOk();

    /** @var array<string, mixed> $body */
    $body = $jsonResponse->json();
    expect($body)->toHaveKeys(['data', 'etag', 'last_modified_at']);

    // All 7 brackets surface in the nodes[] array with winner_participant_id set.
    $nodes = $jsonResponse->json('data.nodes');
    expect($nodes)->toHaveCount(7);
    foreach ($nodes as $node) {
        expect($node['winner_participant_id'])->not->toBeNull();
    }
});
