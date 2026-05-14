<?php

declare(strict_types=1);

use App\Data\Internal\MatchEventInputData;
use App\Services\Rcon\MatchEventNormaliser;

/*
| GREEN — plan 08-07 task 1. Replaces the Wave 0 RED stub from plan 08-01.
|
| Exercises the canonical wire contract between worker's CrconEventNormaliser
| (TS, plan 08-10) and web's MatchEventNormaliser (defence-in-depth shape gate
| before MatchEventIngestService persists). 11 cases per plan 08-07 task 1
| behaviour:
|
|   1. game_start                        — permissive, returns DTO
|   2. round_start                       — permissive, returns DTO
|   3. player_kill (well-formed)         — returns DTO
|   4. player_kill missing weapon        — throws InvalidArgumentException
|   5. player_team_kill (well-formed)    — returns DTO (same shape as kill)
|   6. player_connect (well-formed)      — returns DTO
|   7. player_disconnect missing steam_id_64 → throws
|   8. team_switch (well-formed)         — returns DTO
|   9. round_end (well-formed)           — returns DTO
|  10. match_end (well-formed)           — returns DTO
|  11. unknown event_type                — throws
|
| Threat coverage: T-08-07-01 (malformed payload poisons stream) — every negative
| case here is a worker bug the normaliser MUST surface as a bubble-up exception.
*/

/**
 * Build a canonical event envelope around a payload. Helper kept lexical (not a
 * Pest closure-state property) because PHPStan can't infer `$this->prop` for
 * Pest's PendingCalls / TestCall surface — inline `new MatchEventNormaliser`
 * per test is cheap (the class has zero state).
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function neventBase(string $eventType, array $payload): array
{
    return [
        'crcon_stream_id' => '1711657986-' . random_int(0, 9999),
        'event_type' => $eventType,
        'crcon_action' => 'TEST',
        'payload' => $payload,
        'occurred_at' => '2026-05-14T12:00:00Z',
    ];
}

it('accepts game_start with {map, mode} and returns DTO', function (): void {
    $dto = (new MatchEventNormaliser)->validate(neventBase('game_start', ['map' => 'Foy', 'mode' => 'Warfare']));

    expect($dto)->toBeInstanceOf(MatchEventInputData::class);
    expect($dto->event_type)->toBe('game_start');
    expect($dto->payload)->toBe(['map' => 'Foy', 'mode' => 'Warfare']);
});

it('accepts round_start with {round_number} and returns DTO', function (): void {
    $dto = (new MatchEventNormaliser)->validate(neventBase('round_start', ['round_number' => 1]));

    expect($dto)->toBeInstanceOf(MatchEventInputData::class);
    expect($dto->event_type)->toBe('round_start');
});

it('accepts player_kill with killer/victim/weapon and returns DTO', function (): void {
    $payload = [
        'killer' => ['steam_id_64' => '76561198000000001', 'name' => 'A'],
        'victim' => ['steam_id_64' => '76561198000000002', 'name' => 'B'],
        'weapon' => 'KARABINER 98K',
    ];
    $dto = (new MatchEventNormaliser)->validate(neventBase('player_kill', $payload));

    expect($dto)->toBeInstanceOf(MatchEventInputData::class);
    expect($dto->event_type)->toBe('player_kill');
    expect($dto->payload)->toBe($payload);
});

it('throws InvalidArgumentException for player_kill missing weapon', function (): void {
    $payload = [
        'killer' => ['steam_id_64' => '76561198000000001', 'name' => 'A'],
        'victim' => ['steam_id_64' => '76561198000000002', 'name' => 'B'],
        // weapon missing
    ];

    expect(fn (): MatchEventInputData => (new MatchEventNormaliser)->validate(neventBase('player_kill', $payload)))
        ->toThrow(InvalidArgumentException::class, 'weapon');
});

it('accepts player_team_kill with the kill payload shape and returns DTO', function (): void {
    $payload = [
        'killer' => ['steam_id_64' => '76561198000000001', 'name' => 'A'],
        'victim' => ['steam_id_64' => '76561198000000002', 'name' => 'B'],
        'weapon' => 'M1 GARAND',
    ];
    $dto = (new MatchEventNormaliser)->validate(neventBase('player_team_kill', $payload));

    expect($dto)->toBeInstanceOf(MatchEventInputData::class);
    expect($dto->event_type)->toBe('player_team_kill');
});

it('accepts player_connect with {steam_id_64, name} and returns DTO', function (): void {
    $dto = (new MatchEventNormaliser)->validate(neventBase('player_connect', [
        'steam_id_64' => '76561198000000001',
        'name' => 'A',
    ]));

    expect($dto)->toBeInstanceOf(MatchEventInputData::class);
    expect($dto->event_type)->toBe('player_connect');
});

it('throws InvalidArgumentException for player_disconnect missing steam_id_64', function (): void {
    expect(fn (): MatchEventInputData => (new MatchEventNormaliser)->validate(neventBase('player_disconnect', ['name' => 'A'])))
        ->toThrow(InvalidArgumentException::class, 'steam_id_64');
});

it('accepts team_switch with {steam_id_64, name, from_team, to_team} and returns DTO', function (): void {
    $payload = [
        'steam_id_64' => '76561198000000001',
        'name' => 'A',
        'from_team' => 'allies',
        'to_team' => 'axis',
    ];
    $dto = (new MatchEventNormaliser)->validate(neventBase('team_switch', $payload));

    expect($dto)->toBeInstanceOf(MatchEventInputData::class);
    expect($dto->event_type)->toBe('team_switch');
    expect($dto->payload)->toBe($payload);
});

it('accepts round_end with {winning_team, allies_score, axis_score} and returns DTO', function (): void {
    $payload = ['winning_team' => 'allies', 'allies_score' => 5, 'axis_score' => 3];
    $dto = (new MatchEventNormaliser)->validate(neventBase('round_end', $payload));

    expect($dto)->toBeInstanceOf(MatchEventInputData::class);
    expect($dto->event_type)->toBe('round_end');
});

it('accepts match_end with the result payload shape and returns DTO', function (): void {
    $payload = ['winning_team' => 'allies', 'allies_score' => 5, 'axis_score' => 3];
    $dto = (new MatchEventNormaliser)->validate(neventBase('match_end', $payload));

    expect($dto)->toBeInstanceOf(MatchEventInputData::class);
    expect($dto->event_type)->toBe('match_end');
});

it('throws InvalidArgumentException for an unknown event_type', function (): void {
    expect(fn (): MatchEventInputData => (new MatchEventNormaliser)->validate(neventBase('totally_made_up', ['x' => 1])))
        ->toThrow(InvalidArgumentException::class, 'unknown event_type');
});
