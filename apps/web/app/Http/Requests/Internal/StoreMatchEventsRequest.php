<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Source: .planning/phases/08-rcon-automation/08-06-PLAN.md <interfaces>
 *         StoreMatchEventsRequest rules block.
 *
 * Validates POST /api/internal/match/{match}/events bodies. The
 * `rcon.signature` middleware (plan 08-05) is the auth gate — this FormRequest
 * only validates SHAPE.
 *
 * Constraints:
 *  - `events` array — max 100 per batch (CON-rcon-batching). Worker accumulates
 *    up to 100 events OR 5s window then flushes.
 *  - `event_type` MUST be one of the 10 canonical values mirrored in:
 *      * MatchEvent CHECK constraint (DB-side defence in depth)
 *      * lang/en/rcon.php events.types.* keys (UI display)
 *      * apps/rcon-worker/src/normaliser.ts (Node-side enum)
 *  - `payload` is REQUIRED (even for synthesised manual_error events the worker
 *    emits a body) — empty payload is a normaliser bug, not a legal shape.
 *  - `occurred_at` MUST be ISO-8601 parseable; Laravel's `date` rule accepts the
 *    JS Date toISOString() format the worker emits.
 *
 * authorize() returns true — HMAC middleware already authorised at the route level.
 */
final class StoreMatchEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.crcon_stream_id' => ['nullable', 'string', 'max:128'],
            'events.*.event_type' => ['required', 'string', Rule::in([
                'game_start',
                'round_start',
                'player_kill',
                'player_team_kill',
                'player_connect',
                'player_disconnect',
                'team_switch',
                'round_end',
                'match_end',
                'manual_error',
            ])],
            'events.*.crcon_action' => ['nullable', 'string', 'max:128'],
            'events.*.payload' => ['required', 'array'],
            'events.*.occurred_at' => ['required', 'date'],
        ];
    }
}
