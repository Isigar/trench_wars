<?php

declare(strict_types=1);

namespace App\Http\Requests\Matches;

use App\Models\GameMatch;
use App\Models\MatchSlot;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /matches/{match}/disputes (auth-gated).
 *
 * authorize() is the reachability gate for raising a dispute:
 *   - the match must be `played` (there is an outcome to contest), AND
 *   - the raiser must be the organiser OR a participant who occupies a slot.
 * A non-eligible authenticated user gets 403; guests are stopped earlier by the
 * `auth` middleware. The raising user is read from $this->user() server-side —
 * never from the body (no raised_by field exists here).
 *
 * The "one open dispute per (match, user)" rule is enforced by the partial
 * UNIQUE index via DisputeService::open; this request only covers structural
 * eligibility + body validity.
 */
class StoreMatchDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $match = $this->route('match');
        if (! $match instanceof GameMatch) {
            return false;
        }

        // Disputes contest a played match's outcome.
        if ($match->status !== 'played') {
            return false;
        }

        // The organiser may always dispute.
        if ($match->organiser_user_id === $user->id) {
            return true;
        }

        // Otherwise the raiser must have participated (occupied a slot).
        return MatchSlot::query()
            ->where('match_id', $match->id)
            ->where('occupant_user_id', $user->id)
            ->exists();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
