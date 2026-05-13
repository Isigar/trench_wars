<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Source: 05-04-PLAN.md <interfaces> StoreBotMatchSignupRequest block.
 *
 * Validates POST /api/bot/matches/{match}/signups. The signing user is
 * resolved server-side via bot.acts-as middleware — auth()->user() in the
 * controller is the rebound human, NEVER read from the request body
 * (T-04-10-03 / T-05-04-01 mitigation).
 *
 * authorize(): returns true unconditionally. The Sanctum stack (auth:sanctum
 * + abilities:bot:act-as-user) is the actual auth gate, and FormRequest's
 * authorize() only runs AFTER the middleware so a guest never reaches here.
 */
final class StoreBotMatchSignupRequest extends FormRequest
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
            'game_role_id' => ['required', 'uuid', 'exists:game_roles,id'],
        ];
    }
}
