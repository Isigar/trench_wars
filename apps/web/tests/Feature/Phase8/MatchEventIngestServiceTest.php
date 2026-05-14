<?php

declare(strict_types=1);

use App\Jobs\Rcon\CloseMatchJob;
use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Services\Rcon\MatchEventIngestService;
use App\Services\Rcon\MatchEventNormaliser;
use Illuminate\Support\Facades\Bus;

/*
| GREEN — plan 08-07 task 2.
|
| Exercises the canonical ingest seam between the HMAC-protected controller and
| the match_events stream. Six cases per plan 08-07 task 2 behaviour:
|
|   1. Ingest 3 fresh events → 3 rows persisted; accepted=3, skipped=0.
|   2. Ingest 3 events twice → first batch 3/0; second batch 0/3 (UNIQUE absorb).
|   3. Mixed-collision batch (5 events, 2 collide) → accepted=3, skipped=2.
|   4. Batch containing match_end → CloseMatchJob dispatched with $matchId.
|   5. Batch WITHOUT match_end → CloseMatchJob NOT dispatched.
|   6. Malformed payload mid-batch → InvalidArgumentException bubbles; partial
|      commits before the bad index persist (worker resend resumes via UNIQUE
|      absorb — T-08-07-02 disposition: accept).
|
| Threat coverage: T-08-07-01 (malformed poisons stream — case 6), T-08-07-02
| (worker resends duplicate — cases 2 + 3).
*/

beforeEach(function (): void {
    Bus::fake();
});

it('persists 3 fresh events with accepted_count=3 and skipped_count=0', function (): void {
    $match = GameMatch::factory()->create();
    $events = [
        kEvent('game_start', 'stream-001', ['map' => 'Foy', 'mode' => 'Warfare']),
        kEvent('round_start', 'stream-002', ['round_number' => 1]),
        kEvent('player_connect', 'stream-003', ['steam_id_64' => '76561198000000010', 'name' => 'Alpha']),
    ];

    $result = (new MatchEventIngestService(new MatchEventNormaliser))->ingest($match, $events);

    expect($result['batch_id'])->toBeString();
    expect($result['accepted_count'])->toBe(3);
    expect($result['skipped_count'])->toBe(0);
    expect(MatchEvent::where('match_id', $match->id)->count())->toBe(3);
});

it('absorbs a fully-duplicated second batch with skipped_count=3', function (): void {
    $match = GameMatch::factory()->create();
    $events = [
        kEvent('game_start', 'stream-010', ['map' => 'Foy', 'mode' => 'Warfare']),
        kEvent('round_start', 'stream-011', ['round_number' => 1]),
        kEvent('player_connect', 'stream-012', ['steam_id_64' => '76561198000000010', 'name' => 'Alpha']),
    ];

    $service = new MatchEventIngestService(new MatchEventNormaliser);

    $first = $service->ingest($match, $events);
    $second = $service->ingest($match, $events);

    expect($first['accepted_count'])->toBe(3);
    expect($first['skipped_count'])->toBe(0);

    expect($second['accepted_count'])->toBe(0);
    expect($second['skipped_count'])->toBe(3);

    // Only the original 3 rows survive (UNIQUE absorbs the replay).
    expect(MatchEvent::where('match_id', $match->id)->count())->toBe(3);
});

it('handles a mixed-collision batch (3 fresh + 2 duplicates) with accepted=3 skipped=2', function (): void {
    $match = GameMatch::factory()->create();

    // Seed 2 events the worker will later resend as part of a bigger batch.
    $seed = [
        kEvent('game_start', 'stream-020', ['map' => 'Foy', 'mode' => 'Warfare']),
        kEvent('round_start', 'stream-021', ['round_number' => 1]),
    ];
    $service = new MatchEventIngestService(new MatchEventNormaliser);
    $service->ingest($match, $seed);

    // Worker reconnects + resends the 2 already-seen events plus 3 new ones.
    $bigBatch = [
        kEvent('game_start', 'stream-020', ['map' => 'Foy', 'mode' => 'Warfare']),     // dup
        kEvent('round_start', 'stream-021', ['round_number' => 1]),                    // dup
        kEvent('player_connect', 'stream-022', ['steam_id_64' => '76561198000000010', 'name' => 'Alpha']),
        kEvent('player_connect', 'stream-023', ['steam_id_64' => '76561198000000011', 'name' => 'Bravo']),
        kEvent('round_end', 'stream-024', ['winning_team' => 'allies', 'allies_score' => 3, 'axis_score' => 1]),
    ];

    $result = $service->ingest($match, $bigBatch);

    expect($result['accepted_count'])->toBe(3);
    expect($result['skipped_count'])->toBe(2);
    expect(MatchEvent::where('match_id', $match->id)->count())->toBe(5);
});

it('dispatches CloseMatchJob when a batch contains a match_end event', function (): void {
    $match = GameMatch::factory()->create();
    $events = [
        kEvent('round_end', 'stream-030', ['winning_team' => 'allies', 'allies_score' => 5, 'axis_score' => 3]),
        kEvent('match_end', 'stream-031', ['winning_team' => 'allies', 'allies_score' => 5, 'axis_score' => 3]),
    ];

    (new MatchEventIngestService(new MatchEventNormaliser))->ingest($match, $events);

    Bus::assertDispatched(
        CloseMatchJob::class,
        fn (CloseMatchJob $job): bool => $job->matchId === $match->id,
    );
});

it('does NOT dispatch CloseMatchJob when the batch has no match_end event', function (): void {
    $match = GameMatch::factory()->create();
    $events = [
        kEvent('game_start', 'stream-040', ['map' => 'Foy', 'mode' => 'Warfare']),
        kEvent('round_start', 'stream-041', ['round_number' => 1]),
        kEvent('round_end', 'stream-042', ['winning_team' => 'allies', 'allies_score' => 3, 'axis_score' => 2]),
    ];

    (new MatchEventIngestService(new MatchEventNormaliser))->ingest($match, $events);

    Bus::assertNotDispatched(CloseMatchJob::class);
});

it('bubbles InvalidArgumentException on malformed payload and persists events before the bad index', function (): void {
    $match = GameMatch::factory()->create();
    $events = [
        kEvent('game_start', 'stream-050', ['map' => 'Foy', 'mode' => 'Warfare']),
        // Good event — should persist.
        kEvent('player_connect', 'stream-051', ['steam_id_64' => '76561198000000010', 'name' => 'Alpha']),
        // Bad event — kill payload missing weapon → normaliser throws.
        kEvent('player_kill', 'stream-052', [
            'killer' => ['steam_id_64' => '76561198000000010', 'name' => 'Alpha'],
            'victim' => ['steam_id_64' => '76561198000000011', 'name' => 'Bravo'],
            // weapon missing
        ]),
        // Would-be-good event AFTER the bad one — should NOT persist (loop aborted).
        kEvent('round_end', 'stream-053', ['winning_team' => 'allies', 'allies_score' => 3, 'axis_score' => 2]),
    ];

    $service = new MatchEventIngestService(new MatchEventNormaliser);

    $threw = false;
    try {
        $service->ingest($match, $events);
    } catch (InvalidArgumentException $e) {
        $threw = true;
        expect($e->getMessage())->toContain('weapon');
    }
    expect($threw)->toBeTrue();

    // First 2 events persisted; 3rd threw; 4th never ran. Worker resend will
    // pick up the post-bad events via UNIQUE absorb on the first 2.
    $persisted = MatchEvent::where('match_id', $match->id)->pluck('crcon_stream_id')->toArray();
    expect($persisted)->toHaveCount(2);
    expect($persisted)->toContain('stream-050');
    expect($persisted)->toContain('stream-051');
});

/**
 * Build a canonical event envelope. Helper kept lexical (not Pest closure-state)
 * for PHPStan-cleanness on the TestCall surface.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function kEvent(string $eventType, string $streamId, array $payload): array
{
    return [
        'crcon_stream_id' => $streamId,
        'event_type' => $eventType,
        'crcon_action' => 'TEST',
        'payload' => $payload,
        'occurred_at' => '2026-05-14T12:00:00Z',
    ];
}
