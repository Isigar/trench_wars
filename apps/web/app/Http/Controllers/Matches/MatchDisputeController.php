<?php

declare(strict_types=1);

namespace App\Http\Controllers\Matches;

use App\Exceptions\DisputeAlreadyOpenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Matches\StoreMatchDisputeRequest;
use App\Models\GameMatch;
use App\Models\User;
use App\Services\DisputeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * POST /matches/{match}/disputes — the (previously missing) reachable entry
 * point for raising a match dispute.
 *
 * DisputeService::open + the MatchDisputeResource moderator queue + the
 * transition state machine all shipped in Phase 9, but open() had no production
 * caller — a match_disputes row could only be created by a direct DB insert, so
 * no user could ever file a dispute and the moderator queue had zero reachable
 * input. This controller closes that loop.
 *
 * Eligibility + body validity live in StoreMatchDisputeRequest. The
 * one-open-dispute-per-(match,user) rule is enforced by the partial UNIQUE index
 * surfaced as DisputeAlreadyOpenException → a field validation error.
 */
final class MatchDisputeController extends Controller
{
    public function store(StoreMatchDisputeRequest $request, GameMatch $match, DisputeService $service): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $service->open($match, $user, (string) $request->validated('body'));
        } catch (DisputeAlreadyOpenException) {
            throw ValidationException::withMessages([
                'body' => [__('matches.dispute.already_open')],
            ]);
        }

        return redirect()
            ->route('matches.show', $match->id)
            ->with('success', __('matches.dispute.submitted'));
    }
}
