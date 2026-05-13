<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameMatchType;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Models\User;
use App\Services\MatchSignupService;
use Illuminate\Support\Facades\DB;

/*
| Source: 04-06-PLAN.md Task 2 + 04-RESEARCH.md Pitfall 4 + Assumption A8.
| Replaces Wave 0 RED stub. Asserts SC-2 (D-010) — under real parallel
| process contention, exactly one signup wins the last slot and the loser
| receives CapacityExceededException; the DB's occupied-slot count never
| exceeds capacity.
|
| Pre-flight (preserved from plan 04-01 Wave 0 stub):
|   `docker compose exec web php -m | grep pcntl` → `pcntl` extension is
|   PRESENT in the trenchwars-web container PHP 8.4 image (verified during
|   plan 04-01 Wave 0). Assumption A8 confirmed; primary pcntl_fork()
|   approach is viable, fallback dual-connection DB alias unnecessary on
|   this image. The first-line `extension_loaded('pcntl')` guard inside
|   each `it()` block is defensive in case a future image rebuild loses the
|   extension; on a missing-pcntl image the test marks SKIPPED with the
|   fallback documented inline (RESEARCH Pitfall 4 option 2).
|
| RefreshDatabase override (Pitfall 4 — necessary):
|   The global Pest.php `uses(RefreshDatabase::class)->in('Feature')` wraps
|   every Feature test in a transaction that's rolled back at teardown.
|   pcntl_fork BREAKS this — child processes open new Postgres connections
|   that cannot see the parent's UNCOMMITTED setup rows. Workaround: we
|   manually `DB::commit()` the RefreshDatabase setup transaction at the
|   top of each `it()` block, run the test (which writes real rows), then
|   in `afterEach` truncate every match/clan/user table so the next test
|   starts clean. Same precedent as the standard Laravel `DatabaseTruncation`
|   trait, applied surgically to one file.
|
| NAMING NOTE (D-04-03-A): the Match model class is `GameMatch` (NOT
| `Match` — PHP 8.4 parse error). Tests import `use App\Models\GameMatch;`
| directly per D-04-04-C / D-04-05-B canonical Phase 4 idiom.
*/

afterEach(function (): void {
    // RefreshDatabase's rollback is a no-op (we committed); explicitly
    // truncate every table the test touched so the next test (with its own
    // fresh RefreshDatabase transaction) sees a clean DB. Truncate cascades
    // across FK chains: matches → match_slots, users → match_slots.occupant.
    if (DB::transactionLevel() > 0) {
        // RefreshDatabase began a transaction during setUp; we committed
        // mid-test, but Postgres still tracks the wrapper level. Force-end.
        try {
            DB::rollBack();
        } catch (Throwable) {
            // Already-committed transactions throw on rollback — fine.
        }
    }
    DB::statement('TRUNCATE TABLE
        match_access_rules,
        match_slots,
        matches,
        game_match_type_role_limits,
        game_match_types,
        game_roles,
        games,
        clan_memberships,
        clan_invites,
        clan_applications,
        clan_clan_tag,
        clan_tags,
        clans,
        activity_log,
        users
        RESTART IDENTITY CASCADE');
});

/**
 * Build a single-game (match, role) fixture with `slotCapacity` empty slots
 * already materialised, status='open', no access rules (open to all),
 * then COMMIT so forked children see the rows via their own connections.
 *
 * @return array{0: GameMatch, 1: GameRole, 2: list<User>}
 */
function buildConcurrencyFixture(int $slotCapacity, int $userCount): array
{
    $game = Game::factory()->create();
    $matchType = GameMatchType::factory()->for($game)->create();
    $role = GameRole::factory()->for($game)->create();
    $match = GameMatch::factory()->for($matchType, 'gameMatchType')->create(['status' => 'open']);

    for ($i = 0; $i < $slotCapacity; $i++) {
        MatchSlot::factory()->create([
            'match_id' => $match->id,
            'game_role_id' => $role->id,
            'slot_index' => $i,
            'occupant_user_id' => null,
            'confirmed_at' => null,
            'sort_order' => 0,
        ]);
    }

    /** @var list<User> $users */
    $users = User::factory()->count($userCount)->create()->all();

    // Commit so forked children see all setup rows via their own connections.
    // RefreshDatabase began a transaction during setUp — commit it here.
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }

    return [$match, $role, $users];
}

// ---------------------------------------------------------------------------
// SC-2 primary proof — 2 children race for the last slot; exactly 1 succeeds
// ---------------------------------------------------------------------------

it('serializes 2 parallel signups for the last slot — exactly one succeeds', function (): void {
    if (! extension_loaded('pcntl')) {
        $this->markTestSkipped(
            'pcntl extension not available — fallback to dual-connection DB approach documented in 04-RESEARCH.md Pitfall 4 option 2.'
        );
    }

    [$match, $role, $users] = buildConcurrencyFixture(slotCapacity: 1, userCount: 2);
    [$userA, $userB] = $users;

    $childA = pcntl_fork();
    if ($childA === 0) {
        DB::reconnect();
        try {
            app(MatchSignupService::class)->signup($match, $userA, $role);
            exit(0);
        } catch (Throwable) {
            exit(1);
        }
    }

    $childB = pcntl_fork();
    if ($childB === 0) {
        DB::reconnect();
        try {
            app(MatchSignupService::class)->signup($match, $userB, $role);
            exit(0);
        } catch (Throwable) {
            exit(1);
        }
    }

    pcntl_waitpid($childA, $statusA);
    pcntl_waitpid($childB, $statusB);

    $successes = (pcntl_wexitstatus($statusA) === 0 ? 1 : 0)
        + (pcntl_wexitstatus($statusB) === 0 ? 1 : 0);

    // Re-read fresh from the DB (children committed on their own connections).
    DB::reconnect();
    $occupiedCount = MatchSlot::where('match_id', $match->id)
        ->where('game_role_id', $role->id)
        ->whereNotNull('occupant_user_id')
        ->count();

    expect($successes)->toBe(1)
        ->and($occupiedCount)->toBe(1);
});

// ---------------------------------------------------------------------------
// SC-2 N-vs-M stress — 5 children race for 3-capacity role; exactly 3 succeed
// ---------------------------------------------------------------------------

it('serializes 5 parallel signups for a 3-capacity role — exactly 3 succeed', function (): void {
    if (! extension_loaded('pcntl')) {
        $this->markTestSkipped(
            'pcntl extension not available — fallback to dual-connection DB approach documented in 04-RESEARCH.md Pitfall 4 option 2.'
        );
    }

    [$match, $role, $users] = buildConcurrencyFixture(slotCapacity: 3, userCount: 5);

    /** @var list<int> $childPids */
    $childPids = [];
    foreach ($users as $i => $user) {
        $pid = pcntl_fork();
        if ($pid === 0) {
            DB::reconnect();
            try {
                app(MatchSignupService::class)->signup($match, $user, $role);
                exit(0);
            } catch (Throwable) {
                exit(1);
            }
        }
        $childPids[] = $pid;
    }

    $successes = 0;
    foreach ($childPids as $pid) {
        pcntl_waitpid($pid, $status);
        if (pcntl_wexitstatus($status) === 0) {
            $successes++;
        }
    }

    DB::reconnect();
    $occupiedCount = MatchSlot::where('match_id', $match->id)
        ->where('game_role_id', $role->id)
        ->whereNotNull('occupant_user_id')
        ->count();

    expect($successes)->toBe(3)
        ->and($occupiedCount)->toBe(3);
});
