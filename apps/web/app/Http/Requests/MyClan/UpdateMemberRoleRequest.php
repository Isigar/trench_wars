<?php

declare(strict_types=1);

namespace App\Http\Requests\MyClan;

use App\Models\ClanMembership;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Source: 02-09-PLAN.md Task 2 — validates PATCH /my-clan/members/{membership}/role.
 *
 * Security (T-02-05-03 mitigation — defence-in-depth):
 *   - authorize() first delegates to ClanMembershipPolicy::update (same-clan check).
 *   - Then applies the Officer-cannot-promote-to-Leader rule here because the policy
 *     itself does not have access to the desired new role at gate-check time.
 */
class UpdateMemberRoleRequest extends FormRequest
{
    /**
     * Check policy gate AND the Officer-cannot-promote-to-Leader rule.
     *
     * Return false for either condition to produce a 403 response.
     */
    public function authorize(): bool
    {
        /** @var ClanMembership $membership */
        $membership = $this->route('membership');

        // Primary check: actor must be Leader or Officer in the same clan.
        if (! ($this->user()?->can('update', $membership) ?? false)) {
            return false;
        }

        // Defence-in-depth: Officer cannot promote anyone to Leader.
        // (The role field has not been validated at this point so we use
        // $this->input() rather than $this->validated() to read the raw value.)
        $desiredRole = $this->input('role');

        if ($desiredRole === 'leader') {
            // Only a Leader may set another member's role to 'leader'.
            /** @var User $user */
            $user = $this->user();
            $actorMembership = ClanMembership::where('user_id', $user->id)
                ->where('clan_id', $membership->clan_id)
                ->whereNull('left_at')
                ->first();

            if ($actorMembership === null || $actorMembership->role !== 'leader') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'in:leader,officer,member,recruit'],
        ];
    }
}
