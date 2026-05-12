<?php

declare(strict_types=1);

namespace App\Http\Requests\MyClan;

use App\Models\ClanMembership;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Source: 02-10-PLAN.md Task 1.
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'invited_user_id' => ['required', 'uuid', 'exists:users,id'],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }
}
