<?php

declare(strict_types=1);

use App\Jobs\Rcon\TestMatchServerConnectionJob;
use App\Models\MatchServer;
use App\Services\Rcon\CrconHealthProbe;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
| Source: .planning/phases/08-rcon-automation/08-09-PLAN.md task 2.
|
| Verifies the async CRCON health probe wired by the Filament Test Connection
| action. Cases:
|   1. Probe succeeds (CRCON returns 200) → last_test_status='ok', last_test_at
|      ~ now(), last_test_error=null.
|   2. Probe gets 401 → last_test_status='error', last_test_error =
|      translated 'rcon.errors.auth_failed'.
|   3. Probe gets 500 → last_test_status='error', last_test_error =
|      translated 'rcon.errors.unreachable'.
|   4. Probe throws ConnectionException → last_test_status='error',
|      last_test_error = translated 'rcon.errors.unreachable'.
|   5. Server has empty/null api_token → returns 'permission_denied' without
|      issuing an HTTP call (Http::assertNothingSent()).
|
| Analog: tests/Feature/Phase8/MatchEventNormaliserContractTest.php (Phase 8
| stateless-service idiom) + Phase 5 outbox tests for the Http facade.
*/

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('Case 1 — probe succeeds (CRCON 200) sets last_test_status=ok', function (): void {
    $server = MatchServer::factory()->create([
        'host' => 'crcon-eu-01.example.com',
        'port_rcon' => 8010,
        'credentials_encrypted' => ['api_token' => 'fake-bearer-token-200'],
    ]);

    Http::fake([
        'crcon-eu-01.example.com:8010/api/get_map_rotation' => Http::response([
            'result' => ['ELALAMEIN_OFFENSIVE', 'STMEREEGLISE_WARFARE'],
        ], 200),
    ]);

    (new TestMatchServerConnectionJob($server->id))->handle(app(CrconHealthProbe::class));

    $fresh = $server->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->last_test_status)->toBe('ok');
    expect($fresh->last_test_error)->toBeNull();
    expect($fresh->last_test_at)->not->toBeNull();
});

it('Case 2 — probe gets 401 sets last_test_status=error + auth_failed key', function (): void {
    $server = MatchServer::factory()->create([
        'host' => 'crcon-eu-01.example.com',
        'port_rcon' => 8010,
        'credentials_encrypted' => ['api_token' => 'rotated-token'],
    ]);

    Http::fake([
        'crcon-eu-01.example.com:8010/api/get_map_rotation' => Http::response(
            ['error' => 'unauthorised'],
            401,
        ),
    ]);

    (new TestMatchServerConnectionJob($server->id))->handle(app(CrconHealthProbe::class));

    $fresh = $server->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->last_test_status)->toBe('error');
    expect($fresh->last_test_error)->toBe((string) __('rcon.errors.auth_failed'));
});

it('Case 3 — probe gets 500 sets last_test_status=error + unreachable key', function (): void {
    $server = MatchServer::factory()->create([
        'host' => 'crcon-eu-01.example.com',
        'port_rcon' => 8010,
        'credentials_encrypted' => ['api_token' => 'bearer-500'],
    ]);

    Http::fake([
        'crcon-eu-01.example.com:8010/api/get_map_rotation' => Http::response(
            ['error' => 'internal server error'],
            500,
        ),
    ]);

    (new TestMatchServerConnectionJob($server->id))->handle(app(CrconHealthProbe::class));

    $fresh = $server->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->last_test_status)->toBe('error');
    expect($fresh->last_test_error)->toBe((string) __('rcon.errors.unreachable'));
});

it('Case 4 — probe throws ConnectionException sets last_test_status=error + unreachable key', function (): void {
    $server = MatchServer::factory()->create([
        'host' => 'crcon-eu-01.example.com',
        'port_rcon' => 8010,
        'credentials_encrypted' => ['api_token' => 'bearer-timeout'],
    ]);

    // Throw a ConnectionException on the first request (CRCON unreachable / DNS fail).
    Http::fake(function (Request $request): never {
        throw new ConnectionException('cURL error 28: Connection timed out after 10001 milliseconds');
    });

    (new TestMatchServerConnectionJob($server->id))->handle(app(CrconHealthProbe::class));

    $fresh = $server->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->last_test_status)->toBe('error');
    expect($fresh->last_test_error)->toBe((string) __('rcon.errors.unreachable'));
});

it('Case 5 — null/empty api_token returns permission_denied without HTTP call', function (): void {
    $server = MatchServer::factory()->create([
        'host' => 'crcon-eu-01.example.com',
        'port_rcon' => 8010,
        'credentials_encrypted' => ['api_token' => ''],
    ]);

    Http::fake();

    (new TestMatchServerConnectionJob($server->id))->handle(app(CrconHealthProbe::class));

    Http::assertNothingSent();

    $fresh = $server->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->last_test_status)->toBe('error');
    expect($fresh->last_test_error)->toBe((string) __('rcon.errors.permission_denied'));
});
