<?php

declare(strict_types=1);

/*
| GREEN — plan 08-06 task 2.
|
| End-to-end exercise of the three HMAC-protected /api/internal/* routes mounted
| in plan 08-06 task 2:
|   POST /api/internal/match/{match}/events
|   GET  /api/internal/bookings/due
|   GET  /api/internal/match-servers/{server}/credentials
|
| 8 cases covering the wire contract from must_haves.truths + the rcon.signature
| middleware integration:
|   1. GET /bookings/due without HMAC headers   → 401 (middleware mounted)
|   2. GET /bookings/due with valid HMAC, empty → 200 + []
|   3. GET /bookings/due with one active row    → 200 + row with resolved host/port
|   4. GET /match-servers/{uuid}/credentials    → 200 + decrypted api_token
|   5. GET /match-servers/{inactiveUuid}/...    → 404 (active scope)
|   6. POST /match/{uuid}/events 1 event        → 202 + accepted_count=1 (shim path)
|   7. POST /match/{uuid}/events invalid type   → 422 (FormRequest)
|   8. POST /match/{uuid}/events unknown match  → 404 (route binding)
|
| Uses Tests\Support\SignsRconRequests trait to mint per-request HMAC signatures —
| reusable by plans 08-07/08-08/08-12 ingest tests.
|
| Redis::flushdb() in beforeEach so nonce state from previous tests doesn't
| poison the replay-detection logic at the middleware (plan 08-05).
*/

use App\Models\GameMatch;
use App\Models\MatchServer;
use App\Models\MatchServerBooking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\Support\SignsRconRequests;

uses(SignsRconRequests::class);

const PHASE8_INTERNAL_API_TEST_SECRET = 'test-internal-api-secret-for-plan-08-06';

beforeEach(function (): void {
    config(['rcon.hmac_secret' => PHASE8_INTERNAL_API_TEST_SECRET]);
    Redis::flushdb();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('rejects GET /bookings/due without HMAC headers with 401', function (): void {
    // Vanilla GET — no signature headers; middleware MUST refuse.
    $response = $this->getJson('/api/internal/bookings/due');

    expect($response->getStatusCode())->toBe(401);
});

it('returns 200 + [] for GET /bookings/due when no active bookings due', function (): void {
    $response = $this->signedGet('/api/internal/bookings/due');

    expect($response->getStatusCode())->toBe(200);
    expect($response->json())->toBe([]);
});

it('returns 200 + 1 row with resolved server_host/port for an active booking due now', function (): void {
    $server = MatchServer::factory()->create([
        'host' => 'crcon-foy.example.com',
        'port_rcon' => 8011,
    ]);
    $match = GameMatch::factory()->create();
    $booking = MatchServerBooking::factory()
        ->forMatch($match)
        ->onServer($server)
        ->create([
            // Window straddles now() — well inside the controller's ±5min lookahead.
            'reserved_from' => Carbon::now()->subMinute(),
            'reserved_to' => Carbon::now()->addMinutes(2),
            'status' => 'active',
        ]);

    $response = $this->signedGet('/api/internal/bookings/due');

    expect($response->getStatusCode())->toBe(200);
    $body = $response->json();
    expect($body)->toBeArray();
    expect($body)->toHaveCount(1);
    expect($body[0]['id'])->toBe($booking->id);
    expect($body[0]['match_id'])->toBe($match->id);
    expect($body[0]['server_id'])->toBe($server->id);
    expect($body[0]['server_host'])->toBe('crcon-foy.example.com');
    expect($body[0]['server_port'])->toBe(8011);
    expect($body[0]['reserved_from'])->toBeString();
    expect($body[0]['reserved_to'])->toBeString();
});

it('returns 200 + decrypted api_token for GET /match-servers/{uuid}/credentials', function (): void {
    $server = MatchServer::factory()->create([
        'host' => 'crcon-hill.example.com',
        'port_rcon' => 8013,
        'credentials_encrypted' => ['api_token' => 'super-secret-bearer-token-xyz'],
        'is_active' => true,
    ]);

    $response = $this->signedGet("/api/internal/match-servers/{$server->id}/credentials");

    expect($response->getStatusCode())->toBe(200);
    $body = $response->json();
    expect($body)->toBe([
        'host' => 'crcon-hill.example.com',
        'port_rcon' => 8013,
        'api_token' => 'super-secret-bearer-token-xyz',
    ]);
});

it('returns 404 for GET /match-servers/{uuid}/credentials when server is inactive', function (): void {
    $server = MatchServer::factory()->inactive()->create();

    $response = $this->signedGet("/api/internal/match-servers/{$server->id}/credentials");

    expect($response->getStatusCode())->toBe(404);
});

it('returns 202 + accepted_count=1 for POST /match/{uuid}/events with one valid event', function (): void {
    $match = GameMatch::factory()->create();

    $response = $this->signedJsonPost("/api/internal/match/{$match->id}/events", [
        'events' => [[
            'crcon_stream_id' => 'stream-abc-001',
            'event_type' => 'game_start',
            'crcon_action' => null,
            'payload' => ['map' => 'Foy'],
            'occurred_at' => '2026-05-14T12:00:00Z',
        ]],
    ]);

    expect($response->getStatusCode())->toBe(202);
    $body = $response->json();
    expect($body)->toHaveKeys(['batch_id', 'accepted_count']);
    expect($body['accepted_count'])->toBe(1);
    expect($body['batch_id'])->toBeString();
});

it('returns 422 for POST /match/{uuid}/events with invalid event_type', function (): void {
    $match = GameMatch::factory()->create();

    $response = $this->signedJsonPost("/api/internal/match/{$match->id}/events", [
        'events' => [[
            'crcon_stream_id' => 'stream-bad-001',
            // 'foo' is NOT in the canonical 10-value enum → Rule::in fails.
            'event_type' => 'foo',
            'crcon_action' => null,
            'payload' => ['map' => 'Foy'],
            'occurred_at' => '2026-05-14T12:00:00Z',
        ]],
    ]);

    expect($response->getStatusCode())->toBe(422);
});

it('returns 404 for POST /match/{uuid}/events when the match UUID is unknown', function (): void {
    // Fresh UUID never persisted — route model binding fails before the controller.
    $unknownMatchUuid = (string) Str::uuid();

    $response = $this->signedJsonPost("/api/internal/match/{$unknownMatchUuid}/events", [
        'events' => [[
            'crcon_stream_id' => 'stream-orphan-001',
            'event_type' => 'game_start',
            'crcon_action' => null,
            'payload' => ['map' => 'Foy'],
            'occurred_at' => '2026-05-14T12:00:00Z',
        ]],
    ]);

    expect($response->getStatusCode())->toBe(404);
});
