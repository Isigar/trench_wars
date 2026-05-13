<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Source: 05-04-PLAN.md <interfaces> RoleChangeEventRequest block + Pitfall 10
 * echo suppression contract.
 *
 * Validates POST /api/bot/discord-events/role-change. Both snowflake fields are
 * regex-bound to 17-20 digit strings — Discord snowflakes overflow JS `Number`
 * which is why they travel as strings; the regex preserves shape and rejects
 * forged ids before the DB lookup (T-05-04-09 mitigation).
 *
 * authorize(): always true — Sanctum's abilities:bot:reconcile is the auth
 * gate.
 */
final class RoleChangeEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'user_discord_id' => ['required', 'string', 'regex:/^[0-9]{17,20}$/'],
            'role_discord_id' => ['required', 'string', 'regex:/^[0-9]{17,20}$/'],
            'action' => ['required', 'string', 'in:add,remove'],
        ];
    }
}
