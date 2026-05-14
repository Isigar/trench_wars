<?php

declare(strict_types=1);

namespace App\Services\Rcon;

use App\Data\Internal\MatchEventInputData;
use App\Http\Requests\Internal\StoreMatchEventsRequest;
use InvalidArgumentException;

/**
 * Source: .planning/phases/08-rcon-automation/08-07-PLAN.md task 1 + <interfaces>.
 *
 * Web-side canonical-shape validator for incoming CRCON events. This is the
 * defence-in-depth counterpart to the worker's TS-side normaliser
 * (apps/rcon-worker/src/crcon/CrconEventNormaliser.ts — plan 08-10): the
 * worker SHOULD already emit canonical shapes, but a buggy / outdated worker
 * release MUST NOT poison the match_events stream with malformed payloads.
 *
 * Responsibilities:
 *   1. Verify the canonical `payload` shape per `event_type`.
 *   2. Hydrate a typed {@see MatchEventInputData} DTO for downstream persistence.
 *
 * Boundary versus {@see StoreMatchEventsRequest}:
 *   - FormRequest validates ARRAY shape: events[] count, event_type whitelist,
 *     payload is an array, occurred_at parseable.
 *   - Normaliser validates PAYLOAD shape per event_type: a player_kill MUST
 *     carry killer/victim/weapon; a team_switch MUST carry from_team/to_team.
 *
 * Throws {@see InvalidArgumentException} on payload shape miss. The ingest
 * service (plan 08-07 task 2) deliberately does NOT catch this — a shape miss
 * is a worker bug, not a routine error; bubbling up surfaces it as a 500 to
 * trigger operator alert (per T-08-07-01 mitigation).
 *
 * Permissive event types ({@see game_start}, {@see round_start}, {@see manual_error}):
 *   - `game_start` / `round_start` payloads vary by game (HLL ships `{map, mode}`
 *     and `{round_number}` respectively, but Phase 3+ games may add fields).
 *     Strict validation here would couple the normaliser to HLL — out of scope
 *     for the generic Game model (D-007).
 *   - `manual_error` is web-synthesised when the worker can't reach CRCON; the
 *     payload shape is `{kind, detail}` but admin operators may add arbitrary
 *     diagnostic keys. Strict validation would frustrate operations.
 */
final class MatchEventNormaliser
{
    /**
     * Validate an incoming event payload by `event_type` and hydrate the DTO.
     *
     * @param  array<string, mixed>  $event
     *
     * @throws InvalidArgumentException When the payload shape doesn't match the
     *                                  canonical contract for the given event_type
     *                                  or the event_type itself is unknown.
     */
    public function validate(array $event): MatchEventInputData
    {
        $type = $event['event_type'] ?? null;
        /** @var array<string, mixed> $payload */
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];

        match ($type) {
            'player_kill', 'player_team_kill' => $this->assertKillShape($payload),
            'player_connect', 'player_disconnect' => $this->assertPlayerShape($payload),
            'team_switch' => $this->assertTeamSwitchShape($payload),
            'match_end', 'round_end' => $this->assertResultShape($payload),
            // Permissive — generic Game model leeway (see class docblock).
            'game_start', 'round_start', 'manual_error' => null,
            default => throw new InvalidArgumentException(
                'unknown event_type: ' . (is_string($type) ? $type : '<non-string>')
            ),
        };

        return MatchEventInputData::from($event);
    }

    /**
     * player_kill / player_team_kill — `{ killer: { steam_id_64, name }, victim: { steam_id_64, name }, weapon }`.
     *
     * @param  array<string, mixed>  $p
     */
    private function assertKillShape(array $p): void
    {
        $killer = $p['killer'] ?? null;
        $victim = $p['victim'] ?? null;

        if (! is_array($killer) || ! isset($killer['steam_id_64']) || ! is_string($killer['steam_id_64'])) {
            throw new InvalidArgumentException('kill payload missing killer.steam_id_64');
        }
        if (! is_array($victim) || ! isset($victim['steam_id_64']) || ! is_string($victim['steam_id_64'])) {
            throw new InvalidArgumentException('kill payload missing victim.steam_id_64');
        }
        if (! isset($p['weapon']) || ! is_string($p['weapon']) || $p['weapon'] === '') {
            throw new InvalidArgumentException('kill payload missing weapon');
        }
    }

    /**
     * player_connect / player_disconnect — `{ steam_id_64, name }`.
     *
     * @param  array<string, mixed>  $p
     */
    private function assertPlayerShape(array $p): void
    {
        if (! isset($p['steam_id_64']) || ! is_string($p['steam_id_64']) || $p['steam_id_64'] === '') {
            throw new InvalidArgumentException('player payload missing steam_id_64');
        }
        if (! isset($p['name']) || ! is_string($p['name'])) {
            throw new InvalidArgumentException('player payload missing name');
        }
    }

    /**
     * team_switch — `{ steam_id_64, name, from_team, to_team }`.
     *
     * @param  array<string, mixed>  $p
     */
    private function assertTeamSwitchShape(array $p): void
    {
        if (! isset($p['steam_id_64']) || ! is_string($p['steam_id_64']) || $p['steam_id_64'] === '') {
            throw new InvalidArgumentException('team_switch payload missing steam_id_64');
        }
        if (! isset($p['name']) || ! is_string($p['name'])) {
            throw new InvalidArgumentException('team_switch payload missing name');
        }
        if (! isset($p['from_team']) || ! is_string($p['from_team']) || $p['from_team'] === '') {
            throw new InvalidArgumentException('team_switch payload missing from_team');
        }
        if (! isset($p['to_team']) || ! is_string($p['to_team']) || $p['to_team'] === '') {
            throw new InvalidArgumentException('team_switch payload missing to_team');
        }
    }

    /**
     * round_end / match_end — `{ winning_team, allies_score, axis_score }`.
     *
     * Match_end may additionally carry `ended_at` (ISO-8601 string), but it's
     * optional at the normaliser boundary — CloseMatchJob (plan 08-08)
     * synthesises it from `occurred_at` when missing.
     *
     * @param  array<string, mixed>  $p
     */
    private function assertResultShape(array $p): void
    {
        if (! isset($p['winning_team']) || ! is_string($p['winning_team']) || $p['winning_team'] === '') {
            throw new InvalidArgumentException('result payload missing winning_team');
        }
        if (! isset($p['allies_score']) || ! is_int($p['allies_score'])) {
            throw new InvalidArgumentException('result payload missing allies_score');
        }
        if (! isset($p['axis_score']) || ! is_int($p['axis_score'])) {
            throw new InvalidArgumentException('result payload missing axis_score');
        }
    }
}
