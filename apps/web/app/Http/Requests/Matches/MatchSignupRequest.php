<?php

declare(strict_types=1);

namespace App\Http\Requests\Matches;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Source: 04-10-PLAN.md Task 1 + 04-RESEARCH.md Pattern 5 + Pattern 7.
 *
 * Validates POST /matches/{match}/signups (auth-gated). The signup target role
 * is identified by `game_role_id` (UUID); the signing user is read from
 * $request->user() server-side — NEVER from the request body (T-04-10-03).
 *
 * authorize(): always true when authenticated. Guest gating is the auth
 * middleware's job (a guest hitting the route returns 302 to login or 401 in
 * tests). FormRequest's authorize() only runs AFTER the auth middleware so
 * `$this->user()` is guaranteed non-null here.
 *
 * rules(): game_role_id must be a real UUID present in game_roles. The
 * cross-game (role.game !== match.gameMatchType.game) check is a service-
 * layer responsibility — by the time the lookup happens the role-slot
 * relationship will be empty for cross-game pairs so CapacityExceededException
 * naturally fires; the FormRequest covers only structural validity.
 *
 * Threat refs:
 *   T-04-10-03 (signup IDOR via body-supplied user_id) — mitigated structurally:
 *     this FormRequest does not include a user_id field; the controller reads
 *     $request->user() exclusively.
 *   T-04-10-07 (filter param SQL injection) — mitigated: `exists:game_roles,id`
 *     is parameter-bound by the Laravel validator.
 */
class MatchSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
