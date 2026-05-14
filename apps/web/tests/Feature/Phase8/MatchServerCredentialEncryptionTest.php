<?php

declare(strict_types=1);

use App\Models\MatchServer;
use Illuminate\Support\Facades\DB;

/*
| Source: .planning/phases/08-rcon-automation/08-03-PLAN.md task 2.
| Replaces the Wave 0 RED stub from plan 08-01. Asserts that CRCON RCON
| credentials never land as plaintext in the DB and that decryption only
| happens via Laravel's `encrypted:array` cast on the MatchServer model.
|
| Threat mitigated: T-08-03-01 (information disclosure via raw column read).
*/

it('round-trips credentials_encrypted via encrypted:array cast', function (): void {
    $server = MatchServer::factory()->create([
        'credentials_encrypted' => ['api_token' => 'test-token-123'],
    ]);

    $reloaded = MatchServer::query()->findOrFail($server->id);

    expect($reloaded->credentials_encrypted)->toBeArray();
    expect($reloaded->credentials_encrypted['api_token'])->toBe('test-token-123');
});

it('stores credentials_encrypted as ciphertext envelope (not plaintext) at rest', function (): void {
    MatchServer::factory()->create([
        'credentials_encrypted' => ['api_token' => 'plain-secret-value-xyz'],
    ]);

    /** @var string|null $raw */
    $raw = DB::table('match_servers')->value('credentials_encrypted');

    expect($raw)->not->toBeNull();
    // The raw column MUST NOT contain the plaintext token anywhere in its representation.
    expect($raw)->not->toContain('plain-secret-value-xyz');
    // Laravel's Crypt::encryptString envelope is base64-encoded JSON containing the keys
    // iv/value/mac/tag. Any of those substrings indicates the encrypted-cast envelope is intact.
    $decoded = base64_decode((string) $raw, true);
    expect($decoded)->toBeString();
    expect($decoded)->toMatch('/"iv"\s*:\s*"/');
    expect($decoded)->toMatch('/"value"\s*:\s*"/');
    expect($decoded)->toMatch('/"mac"\s*:\s*"/');
});
