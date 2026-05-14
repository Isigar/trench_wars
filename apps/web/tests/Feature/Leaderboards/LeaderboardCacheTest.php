<?php

declare(strict_types=1);

use App\Models\GameMatch;
use App\Models\MatchPlayerStat;
use App\Models\Player;
use App\Services\LeaderboardService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/*
| Source: .planning/phases/09-polish/09-05-PLAN.md task 2.
|
| GREEN replacement for the Wave 0 stub (plan 09-01).
| Asserts SC-2 (cache) — second call returns cached data; flush works.
| Asserts Pitfall 9 (silent SWR refresh) — exception surfaces via report().
|
| phpunit.xml pins CACHE_STORE=array — Cache::tags()->flexible() works on
| array driver (verified during planning research). The Pitfall 1 concern
| ("Cache::tags fails silently on non-Redis store") applies to file/database
| drivers, not array.
*/

beforeEach(function (): void {
    Cache::tags(['leaderboards'])->flush();
});

it('caches topPlayers result on first call', function (): void {
    $match = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(1)]);
    $player = Player::factory()->create();
    MatchPlayerStat::factory()->forMatch($match)->forPlayer($player)->create(['kills' => 5, 'deaths' => 1]);

    $service = app(LeaderboardService::class);

    // Pre-flight: cache miss.
    expect(Cache::tags(['leaderboards', 'lb:players:7d'])->get('lb:players:7d:all:25'))->toBeNull();

    $service->topPlayers('7d');

    // Cache::flexible writes a fresh entry under the requested key. Reading
    // via the same tag namespace must yield a non-null value.
    expect(Cache::tags(['leaderboards', 'lb:players:7d'])->get('lb:players:7d:all:25'))->not->toBeNull();
});

it('returns cached result on second call without re-hitting the aggregate query', function (): void {
    $match = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(1)]);
    $player = Player::factory()->create();
    MatchPlayerStat::factory()->forMatch($match)->forPlayer($player)->create(['kills' => 5, 'deaths' => 1]);

    $service = app(LeaderboardService::class);

    // Warm the cache.
    $first = $service->topPlayers('7d');
    expect($first)->toHaveCount(1);

    // Now count queries during the second call. With a warm cache the
    // service should NOT re-run the SUM(kills) aggregate.
    DB::flushQueryLog();
    DB::enableQueryLog();
    $second = $service->topPlayers('7d');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($second)->toHaveCount(1);
    expect($queryCount)->toBe(0);
});

it('flushes leaderboards cache tag on demand (manual flush)', function (): void {
    $match = GameMatch::factory()->create(['scheduled_at' => Carbon::now()->subDays(1)]);
    $player = Player::factory()->create();
    MatchPlayerStat::factory()->forMatch($match)->forPlayer($player)->create(['kills' => 5, 'deaths' => 1]);

    $service = app(LeaderboardService::class);

    // Warm.
    $service->topPlayers('7d');
    expect(Cache::tags(['leaderboards', 'lb:players:7d'])->get('lb:players:7d:all:25'))->not->toBeNull();

    // Flush.
    Cache::tags(['leaderboards'])->flush();

    expect(Cache::tags(['leaderboards', 'lb:players:7d'])->get('lb:players:7d:all:25'))->toBeNull();
});

it('reports and rethrows when the aggregate compute callback fails (Pitfall 9)', function (): void {
    // Force the compute to fail by injecting a corrupt window assertion path:
    // assertKnownWindow throws InvalidArgumentException BEFORE entering Cache::flexible.
    // For the inner-callback failure we have to trigger an exception from inside
    // the compute. Easiest reproducible path: drop the source table inside a
    // database transaction so the SUM(...) query fails.
    //
    // Pest's RefreshDatabase wraps each test in a transaction; dropping the
    // table mid-test would leak. Use DB::beginTransaction + Schema::drop +
    // assert on the reported exception, then rollback the schema mutation.
    //
    // Cleaner alternative: spy on report() by swapping the ExceptionHandler.
    /** @var Collection<int, Throwable> $reported */
    $reported = collect();
    app()->bind(
        ExceptionHandler::class,
        function () use ($reported): ExceptionHandler {
            return new class($reported) implements ExceptionHandler
            {
                /** @param Collection<int, Throwable> $sink */
                public function __construct(private Collection $sink) {}

                public function report(Throwable $e): void
                {
                    $this->sink->push($e);
                }

                public function shouldReport(Throwable $e): bool
                {
                    return true;
                }

                public function render($request, Throwable $e): Response
                {
                    throw $e;
                }

                public function renderForConsole($output, Throwable $e): void
                {
                    throw $e;
                }
            };
        },
    );

    // Use reflection to invoke the protected safeCompute() with a throwing
    // callback. This isolates Pitfall 9 assertion to the safeCompute primitive
    // without requiring a contrived DB failure.
    $service = app(LeaderboardService::class);
    $ref = new ReflectionClass($service);
    $method = $ref->getMethod('safeCompute');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service, fn () => throw new RuntimeException('boom')))
        ->toThrow(RuntimeException::class, 'boom');

    expect($reported)->toHaveCount(1);
    /** @var Throwable $first */
    $first = $reported->first();
    expect($first)->toBeInstanceOf(RuntimeException::class);
    expect($first->getMessage())->toBe('boom');

    Log::info('Pitfall 9 assertion satisfied — safeCompute reported and rethrew.');
});
