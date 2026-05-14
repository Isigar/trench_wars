<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\MatchEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/08-rcon-automation/08-04-PLAN.md task 1 +
 *         08-RESEARCH.md "Stream ID format" lines 521-534 (canonical event_type
 *         shapes and CRCON stream_id format `{unix_ts}-{incr}`).
 *
 * Replaces the Wave 0 stub (plan 08-01). Each state method yields one of the
 * ten canonical match_event_type rows (see rcon.php events.types.*). Stream
 * IDs auto-increment per-process via the static counter to mirror CRCON's
 * `{unix_timestamp_seconds}-{increment}` contract; tests that need a fixed
 * stream id can pass it explicitly to ->create([...]).
 *
 * @extends Factory<MatchEvent>
 */
class MatchEventFactory extends Factory
{
    protected $model = MatchEvent::class;

    /** Monotonic stream id counter (mirrors CRCON's per-server increment). */
    private static int $streamIdCounter = 0;

    private static function nextStreamId(): string
    {
        return '1711657986-' . (self::$streamIdCounter++);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $killer = (string) fake()->numerify('7656119###########');
        $victim = (string) fake()->numerify('7656119###########');

        return [
            'match_id' => GameMatch::factory(),
            'event_type' => 'player_kill',
            'crcon_action' => 'KILL',
            'crcon_stream_id' => self::nextStreamId(),
            'payload' => [
                'killer' => ['steam_id_64' => $killer, 'name' => fake()->userName()],
                'victim' => ['steam_id_64' => $victim, 'name' => fake()->userName()],
                'weapon' => 'KARABINER 98K',
            ],
            'occurred_at' => now(),
        ];
    }

    /** State: game_start — { map, mode }. */
    public function gameStart(string $map = 'Foy', string $mode = 'Warfare'): self
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => 'game_start',
            'crcon_action' => 'MATCH START',
            'payload' => ['map' => $map, 'mode' => $mode],
        ]);
    }

    /** State: round_start — { round_number }. */
    public function roundStart(int $roundNumber = 1): self
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => 'round_start',
            'crcon_action' => 'ROUND STARTED',
            'payload' => ['round_number' => $roundNumber],
        ]);
    }

    /**
     * State: player_kill — pins killer + victim steam ids and weapon for
     * deterministic assertions.
     */
    public function kill(string $killerSteam, string $victimSteam, string $weapon = 'KARABINER 98K'): self
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => 'player_kill',
            'crcon_action' => 'KILL',
            'payload' => [
                'killer' => ['steam_id_64' => $killerSteam, 'name' => 'Killer-' . $killerSteam],
                'victim' => ['steam_id_64' => $victimSteam, 'name' => 'Victim-' . $victimSteam],
                'weapon' => $weapon,
            ],
        ]);
    }

    /** State: player_team_kill — same payload shape as kill() but distinct event_type. */
    public function teamKill(string $killerSteam, string $victimSteam, string $weapon = 'M1 GARAND'): self
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => 'player_team_kill',
            'crcon_action' => 'TEAM KILL',
            'payload' => [
                'killer' => ['steam_id_64' => $killerSteam, 'name' => 'Killer-' . $killerSteam],
                'victim' => ['steam_id_64' => $victimSteam, 'name' => 'Victim-' . $victimSteam],
                'weapon' => $weapon,
            ],
        ]);
    }

    /** State: player_connect — { steam_id_64, name }. */
    public function connect(string $steam, string $name = 'Connecting'): self
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => 'player_connect',
            'crcon_action' => 'CONNECTED',
            'payload' => ['steam_id_64' => $steam, 'name' => $name],
        ]);
    }

    /** State: player_disconnect — { steam_id_64, name }. */
    public function disconnect(string $steam, string $name = 'Disconnecting'): self
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => 'player_disconnect',
            'crcon_action' => 'DISCONNECTED',
            'payload' => ['steam_id_64' => $steam, 'name' => $name],
        ]);
    }

    /** State: team_switch — { steam_id_64, name, from_team, to_team }. */
    public function teamSwitch(string $steam, string $name, string $fromTeam = 'axis', string $toTeam = 'allies'): self
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => 'team_switch',
            'crcon_action' => 'TEAMSWITCH',
            'payload' => [
                'steam_id_64' => $steam,
                'name' => $name,
                'from_team' => $fromTeam,
                'to_team' => $toTeam,
            ],
        ]);
    }

    /** State: round_end — { winning_team, allies_score, axis_score }. */
    public function roundEnd(string $winningTeam = 'allies', int $alliesScore = 3, int $axisScore = 1): self
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => 'round_end',
            'crcon_action' => 'ROUND ENDED',
            'payload' => [
                'winning_team' => $winningTeam,
                'allies_score' => $alliesScore,
                'axis_score' => $axisScore,
            ],
        ]);
    }

    /** State: match_end — { winning_team, allies_score, axis_score, ended_at }. */
    public function matchEnd(string $winningTeam = 'allies', int $alliesScore = 3, int $axisScore = 2): self
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => 'match_end',
            'crcon_action' => 'MATCH ENDED',
            'payload' => [
                'winning_team' => $winningTeam,
                'allies_score' => $alliesScore,
                'axis_score' => $axisScore,
                'ended_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /** State: manual_error — { kind, detail }. crcon_stream_id stays null since this is web-synthesised. */
    public function manualError(string $kind = 'unreachable', string $detail = 'CRCON connection refused'): self
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => 'manual_error',
            'crcon_action' => null,
            'crcon_stream_id' => null,
            'payload' => ['kind' => $kind, 'detail' => $detail],
        ]);
    }
}
