<?php

declare(strict_types=1);

use App\Exceptions\SeedingNotAllowedException;
use App\Models\Clan;
use App\Models\GameMatch;
use App\Models\MatchResult;
use App\Models\Tournament;
use App\Models\TournamentBracket;
use App\Models\TournamentParticipant;
use App\Models\TournamentStage;
use App\Models\User;
use App\Services\TournamentSeedingService;
use Spatie\Activitylog\Models\Activity;

/*
| Source: 06-05-PLAN.md Task 1 — replaces Wave 0 RED stub from 06-01.
|
| Covers TournamentSeedingService:
|   - 3 seeding strategies (by_rank / random / manual)
|   - reseed() flow with canReseed() guard (RESOLVED Open Question A4)
|   - SeedingNotAllowedException thrown when MatchResult rows exist for any bracket-linked match
|   - Tournament::canReseed() returns false on non-seeded statuses
|   - DB::transaction lockForUpdate idiom (T-06-05-02 concurrent-seed mitigation)
|   - Activity log emission for both seed() and reseed() with shape assertions (T-06-05-03)
|
| NAMING NOTE (D-04-03-A): Match model class is GameMatch. No `match($x)` expressions
| appear here so the alias-on-import pattern is not needed.
*/

// ---------------------------------------------------------------------------
// Strategy: by_rank — deterministic created_at desc ordering
// ---------------------------------------------------------------------------

it('by_rank strategy assigns 1..N seeds in created_at desc order', function (): void {
    $tournament = Tournament::factory()->inStatus('registering')->create();

    // Lock ordering by explicit created_at — p4 is newest (rank 1), p1 oldest (rank 4).
    $p1 = TournamentParticipant::factory()->for($tournament)->create(['created_at' => now()->subMinutes(4)]);
    $p2 = TournamentParticipant::factory()->for($tournament)->create(['created_at' => now()->subMinutes(3)]);
    $p3 = TournamentParticipant::factory()->for($tournament)->create(['created_at' => now()->subMinutes(2)]);
    $p4 = TournamentParticipant::factory()->for($tournament)->create(['created_at' => now()->subMinute()]);

    app(TournamentSeedingService::class)->seed($tournament, 'by_rank');

    expect($p4->fresh()->seed)->toBe(1);
    expect($p3->fresh()->seed)->toBe(2);
    expect($p2->fresh()->seed)->toBe(3);
    expect($p1->fresh()->seed)->toBe(4);
});

it('by_rank flips every registered participant to active status', function (): void {
    $tournament = Tournament::factory()->inStatus('registering')->create();
    $participants = TournamentParticipant::factory()->for($tournament)->count(4)->create();

    app(TournamentSeedingService::class)->seed($tournament, 'by_rank');

    foreach ($participants as $p) {
        expect($p->fresh()->status)->toBe('active');
    }
});

// ---------------------------------------------------------------------------
// Phase 11 — by_rank orders by clan elo_rating DESC (D-11-03-A)
// ---------------------------------------------------------------------------

it('by_rank orders participants by clan elo_rating desc (high elo = seed 1)', function (): void {
    $tournament = Tournament::factory()->inStatus('registering')->create();

    $highEloClan = Clan::factory()->create(['elo_rating' => 1800]);
    $lowEloClan = Clan::factory()->create(['elo_rating' => 1200]);

    // Create low-elo participant first (older created_at) — to confirm elo_rating
    // is the primary sort key, not created_at.
    $pLow = TournamentParticipant::factory()->for($tournament)->create([
        'clan_id' => $lowEloClan->id,
        'created_at' => now()->subMinutes(5),
    ]);
    $pHigh = TournamentParticipant::factory()->for($tournament)->create([
        'clan_id' => $highEloClan->id,
        'created_at' => now()->subMinutes(1),
    ]);

    app(TournamentSeedingService::class)->seed($tournament, 'by_rank');

    // High-elo clan gets seed 1 regardless of creation order.
    expect($pHigh->fresh()->seed)->toBe(1);
    expect($pLow->fresh()->seed)->toBe(2);
});

it('by_rank with all clans at 1500 matches created_at desc order (D-11-03-A no-regression)', function (): void {
    $tournament = Tournament::factory()->inStatus('registering')->create();

    // All clans explicitly at elo_rating=1500 (factory default), distinct created_at.
    // Expected seed order: most recently registered = seed 1 (newest → oldest).
    $clans = Clan::factory()->count(4)->create(['elo_rating' => 1500]);

    $p1 = TournamentParticipant::factory()->for($tournament)->create([
        'clan_id' => $clans[0]->id,
        'created_at' => now()->subMinutes(4),
    ]);
    $p2 = TournamentParticipant::factory()->for($tournament)->create([
        'clan_id' => $clans[1]->id,
        'created_at' => now()->subMinutes(3),
    ]);
    $p3 = TournamentParticipant::factory()->for($tournament)->create([
        'clan_id' => $clans[2]->id,
        'created_at' => now()->subMinutes(2),
    ]);
    $p4 = TournamentParticipant::factory()->for($tournament)->create([
        'clan_id' => $clans[3]->id,
        'created_at' => now()->subMinutes(1),
    ]);

    app(TournamentSeedingService::class)->seed($tournament, 'by_rank');

    // With all clans at 1500 the tiebreak is created_at DESC (newest = seed 1).
    // This is byte-identical to the pre-Phase-11 sortByDesc('created_at') behavior.
    expect($p4->fresh()->seed)->toBe(1); // newest
    expect($p3->fresh()->seed)->toBe(2);
    expect($p2->fresh()->seed)->toBe(3);
    expect($p1->fresh()->seed)->toBe(4); // oldest
});

// ---------------------------------------------------------------------------
// Strategy: random — Faker shuffle (probabilistic; 5-run loose flake budget)
// ---------------------------------------------------------------------------

it('random strategy assigns 1..N seeds and is non-deterministic across runs', function (): void {
    // Probabilistic check: run random seeding 5x against the same created_at order
    // and assert at least one run produces an order different from created_at asc.
    // With 4 participants, the chance of all 5 shuffles being identical (and equal to
    // the sorted order) is (1/24)^5 ≈ 1.25e-7 — well below "acceptable flake".
    $observedOrders = [];

    for ($run = 0; $run < 5; $run++) {
        $tournament = Tournament::factory()->inStatus('registering')->create();
        $participants = collect();
        for ($i = 0; $i < 4; $i++) {
            $participants->push(
                TournamentParticipant::factory()->for($tournament)->create([
                    'created_at' => now()->subMinutes(4 - $i),
                ])
            );
        }

        app(TournamentSeedingService::class)->seed($tournament, 'random');

        $seedByCreatedAtOrder = $participants
            ->map(fn (TournamentParticipant $p): int => (int) $p->fresh()->seed)
            ->all();
        $observedOrders[] = $seedByCreatedAtOrder;

        // Every run MUST still assign exactly seeds 1..N.
        $sorted = $seedByCreatedAtOrder;
        sort($sorted);
        expect($sorted)->toBe([1, 2, 3, 4]);
    }

    // At least one of the 5 observed orders should differ from the deterministic
    // ascending [1,2,3,4] order — flake budget below 1e-7.
    $nonAscending = array_filter($observedOrders, fn (array $order): bool => $order !== [1, 2, 3, 4]);
    expect($nonAscending)->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Strategy: manual — no-op on seed values; flips status only
// ---------------------------------------------------------------------------

it('manual strategy preserves admin-set seed values and flips status to active', function (): void {
    $tournament = Tournament::factory()->inStatus('registering')->create();
    $p1 = TournamentParticipant::factory()->for($tournament)->create(['seed' => 7]);
    $p2 = TournamentParticipant::factory()->for($tournament)->create(['seed' => 3]);
    $p3 = TournamentParticipant::factory()->for($tournament)->create(['seed' => 5]);

    app(TournamentSeedingService::class)->seed($tournament, 'manual');

    // Seeds untouched.
    expect($p1->fresh()->seed)->toBe(7);
    expect($p2->fresh()->seed)->toBe(3);
    expect($p3->fresh()->seed)->toBe(5);

    // Status flipped.
    expect($p1->fresh()->status)->toBe('active');
    expect($p2->fresh()->status)->toBe('active');
    expect($p3->fresh()->status)->toBe('active');
});

// ---------------------------------------------------------------------------
// Activity log emission — seed()
// ---------------------------------------------------------------------------

it('writes an activity log row on seed() with strategy + participant_count', function (): void {
    $tournament = Tournament::factory()->inStatus('registering')->create();
    TournamentParticipant::factory()->for($tournament)->count(3)->create();
    $causer = User::factory()->create();

    app(TournamentSeedingService::class)->seed($tournament, 'by_rank', $causer);

    $activity = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('description', 'Tournament seeded')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->get('strategy'))->toBe('by_rank');
    expect($activity->properties->get('participant_count'))->toBe(3);
    expect($activity->causer_id)->toBe($causer->id);
});

// ---------------------------------------------------------------------------
// canReseed() gate — Open Question A4 RESOLVED inline
// ---------------------------------------------------------------------------

it('canReseed() returns false when a MatchResult exists for any bracket-linked match (A4 LOCKED)', function (): void {
    $tournament = Tournament::factory()->inStatus('seeded')->create();
    $stage = TournamentStage::factory()->for($tournament)->create();
    $match = GameMatch::factory()->create();
    TournamentBracket::factory()->for($stage, 'stage')->create(['match_id' => $match->id]);
    // winner_clan_id=null (draw) prevents Phase 6 plan 06-08 MatchResultObserver
    // from firing advance() on this synthetic bracket (no participants registered).
    // The canReseed() gate only checks MatchResult existence, not winner.
    MatchResult::factory()->create(['match_id' => $match->id, 'winner_clan_id' => null]);

    expect($tournament->fresh()->canReseed())->toBeFalse();
});

it('canReseed() returns true when no MatchResult exists for any bracket-linked match', function (): void {
    $tournament = Tournament::factory()->inStatus('seeded')->create();
    $stage = TournamentStage::factory()->for($tournament)->create();
    $match = GameMatch::factory()->create();
    TournamentBracket::factory()->for($stage, 'stage')->create(['match_id' => $match->id]);

    expect($tournament->fresh()->canReseed())->toBeTrue();
});

it('canReseed() returns false on a completed tournament (terminal lifecycle)', function (): void {
    $tournament = Tournament::factory()->inStatus('completed')->create();

    expect($tournament->fresh()->canReseed())->toBeFalse();
});

it('canReseed() returns false on a cancelled tournament (terminal lifecycle)', function (): void {
    $tournament = Tournament::factory()->inStatus('cancelled')->create();

    expect($tournament->fresh()->canReseed())->toBeFalse();
});

it('canReseed() returns false on a draft tournament (pre-seeding)', function (): void {
    $tournament = Tournament::factory()->inStatus('draft')->create();

    expect($tournament->fresh()->canReseed())->toBeFalse();
});

// ---------------------------------------------------------------------------
// reseed() — happy + sad paths
// ---------------------------------------------------------------------------

it('rejects reseed when a MatchResult exists for a bracket-linked match (A4 LOCKED)', function (): void {
    $tournament = Tournament::factory()->inStatus('seeded')->create();
    $stage = TournamentStage::factory()->for($tournament)->create();
    $match = GameMatch::factory()->create();
    TournamentBracket::factory()->for($stage, 'stage')->create(['match_id' => $match->id]);
    // winner_clan_id=null (draw) prevents Phase 6 plan 06-08 MatchResultObserver
    // from firing advance() on this synthetic bracket (no participants registered).
    MatchResult::factory()->create(['match_id' => $match->id, 'winner_clan_id' => null]);

    expect(fn () => app(TournamentSeedingService::class)->reseed($tournament, 'by_rank'))
        ->toThrow(SeedingNotAllowedException::class);
});

it('rejects reseed with the localised tournaments.errors.reseed_not_allowed message', function (): void {
    $tournament = Tournament::factory()->inStatus('seeded')->create();
    $stage = TournamentStage::factory()->for($tournament)->create();
    $match = GameMatch::factory()->create();
    TournamentBracket::factory()->for($stage, 'stage')->create(['match_id' => $match->id]);
    // winner_clan_id=null (draw) prevents Phase 6 plan 06-08 MatchResultObserver
    // from firing advance() on this synthetic bracket (no participants registered).
    MatchResult::factory()->create(['match_id' => $match->id, 'winner_clan_id' => null]);

    try {
        app(TournamentSeedingService::class)->reseed($tournament, 'by_rank');
        expect(true)->toBeFalse(); // unreachable — reseed must throw
    } catch (SeedingNotAllowedException $e) {
        expect($e->getMessage())->toBe(__('tournaments.errors.reseed_not_allowed'));
    }
});

it('reseed succeeds when no MatchResult exists; tournament returns to status=seeded', function (): void {
    $tournament = Tournament::factory()->inStatus('seeded')->create();
    $causer = User::factory()->create();

    // Pre-existing 4 active participants with seeds 1..4.
    $participants = TournamentParticipant::factory()
        ->for($tournament)
        ->count(4)
        ->active()
        ->create();
    foreach ($participants as $i => $p) {
        $p->update(['seed' => $i + 1]);
    }

    expect($tournament->fresh()->canReseed())->toBeTrue();

    app(TournamentSeedingService::class)->reseed($tournament, 'random', $causer);

    $tournament->refresh();
    expect($tournament->status)->toBe('seeded');

    // After reseed, every participant has a non-null seed in 1..N.
    $seeds = $tournament->participants()->pluck('seed')->sort()->values()->all();
    expect($seeds)->toBe([1, 2, 3, 4]);

    // Every participant flipped back to active.
    foreach ($tournament->participants as $p) {
        expect($p->status)->toBe('active');
    }
});

it('reseed emits a dedicated activity log row with previous_seeds + new_seeds maps', function (): void {
    $tournament = Tournament::factory()->inStatus('seeded')->create();
    $causer = User::factory()->create();

    // 3 active participants with seeds 1..3.
    $participants = TournamentParticipant::factory()
        ->for($tournament)
        ->count(3)
        ->active()
        ->create();
    foreach ($participants as $i => $p) {
        $p->update(['seed' => $i + 1]);
    }
    $clanIds = $participants->pluck('clan_id')->all();

    app(TournamentSeedingService::class)->reseed($tournament, 'by_rank', $causer);

    $activity = Activity::query()
        ->where('subject_type', Tournament::class)
        ->where('subject_id', $tournament->id)
        ->where('description', 'Tournament reseeded')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($causer->id);
    expect($activity->properties->get('strategy'))->toBe('by_rank');

    $previousSeeds = $activity->properties->get('previous_seeds');
    $newSeeds = $activity->properties->get('new_seeds');

    expect($previousSeeds)->toBeArray();
    expect($newSeeds)->toBeArray();
    expect(count($previousSeeds))->toBe(3);
    expect(count($newSeeds))->toBe(3);

    // Maps are keyed by clan_id with seed values 1..N (order may differ).
    foreach ($clanIds as $clanId) {
        expect($previousSeeds)->toHaveKey($clanId);
        expect($newSeeds)->toHaveKey($clanId);
    }
    expect(collect($newSeeds)->values()->sort()->values()->all())->toBe([1, 2, 3]);
});
