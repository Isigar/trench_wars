<?php

declare(strict_types=1);

namespace App\Http\Requests\MyClan;

use App\Models\ClanMembership;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Source: 02-10-PLAN.md Task 1.
 * Rule 2 amendment (02-11-PLAN.md Task 2): accepts invited_username as an
 * alternative to invited_user_id. prepareForValidation() resolves the
 * username to a UUID so the rest of the pipeline is unchanged.
 *
 * Validates POST /my-clan/invites (send an invite to a user).
 *
 * authorize() asserts the actor has an active Leader or Officer membership
 * in any clan (the controller resolves the specific clan server-side).
 */
class StoreClanInviteRequest extends FormRequest
{
    /**
     * Actor must be an active Leader or Officer to send invites.
     */
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->user();

        $membership = ClanMembership::where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();

        if ($membership === null) {
            return false;
        }

        return in_array($membership->role, ['leader', 'officer'], strict: true);
    }

    /**
     * Resolve invited_username -> invited_user_id before validation runs.
     *
     * If the client sends `invited_username` (the My Clan page invite modal sends
     * a plain text username), we look up the user and inject their UUID so
     * downstream rules and controllers work with invited_user_id only.
     */
    protected function prepareForValidation(): void
    {
        $username = $this->input('invited_username');

        if ($username !== null && $this->input('invited_user_id') === null) {
            $resolved = User::where('username', $username)->first();

            $this->merge([
                'invited_user_id' => $resolved?->id,
            ]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'invited_user_id' => ['required', 'uuid', 'exists:users,id'],
            'invited_username' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }
}
