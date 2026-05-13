<?php

declare(strict_types=1);

namespace App\Http\Controllers\Matches;

use App\Exceptions\AlreadySignedUpException;
use App\Exceptions\CapacityExceededException;
use App\Exceptions\MatchNotOpenException;
use App\Exceptions\TagRestrictedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Matches\MatchSignupRequest;
use App\Models\GameMatch;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Models\User;
use App\Services\MatchSignupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Source: 04-10-PLAN.md Task 1 + 04-RESEARCH.md Pattern 2 + Pattern 7.
 *
 * Auth-only POST/DELETE handler for match signups (SC-2 + SC-5 HTTP entry point).
 *
 * Routes (registered in routes/web.php under auth middleware):
 *   POST   /matches/{match}/signups          -> store
 *   DELETE /matches/{match}/signups/{slot}   -> destroy
 *
 * The store handler is a THIN wrapper over MatchSignupService::signup. The
 * service holds the SOLE production write path to match_slots.occupant_user_id
 * (D-010 row-locked transactional capacity). The controller:
 *   1. Validates the FormRequest (game_role_id structurally valid).
 *   2. Resolves GameRole via findOrFail (404 if not found).
 *   3. Calls $service->signup($match, $request->user(), $role).
 *   4. Catches each of the 4 typed exceptions and converts to a 422
 *      ValidationException with a localized error key. Order:
 *        - MatchNotOpenException     → general
 *        - TagRestrictedException    → general
 *        - AlreadySignedUpException  → general
 *        - CapacityExceededException → game_role_id
 *
 * The destroy handler clears (occupant_user_id, confirmed_at) on the viewer's
 * own slot. The slot's match must match {match} (URL param mismatch → 404),
 * and the slot's occupant must equal the viewer (403 otherwise).
 *
 * Threat refs:
 *   T-04-10-03 (signup IDOR): $request->user() is the only User input —
 *     never read from the body.
 *   T-04-10-04 (capacity race): delegated to the service's lockForUpdate.
 *   T-04-10-05 (cross-game game_role_id): the role lookup is structurally
 *     valid (FormRequest); a role from a different game has zero matching
 *     slots so CapacityExceededException fires naturally inside the service.
 */
class MatchSignupController extends Controller
{
    public function store(
        MatchSignupRequest $request,
        GameMatch $match,
        MatchSignupService $service,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        /** @var array{game_role_id: string} $validated */
        $validated = $request->validated();

        $role = GameRole::findOrFail($validated['game_role_id']);

        try {
            $service->signup($match, $user, $role);
        } catch (MatchNotOpenException $e) {
            throw ValidationException::withMessages([
                'general' => [$e->getMessage()],
            ]);
        } catch (TagRestrictedException $e) {
            throw ValidationException::withMessages([
                'general' => [$e->getMessage()],
            ]);
        } catch (AlreadySignedUpException $e) {
            throw ValidationException::withMessages([
                'general' => [$e->getMessage()],
            ]);
        } catch (CapacityExceededException $e) {
            throw ValidationException::withMessages([
                'game_role_id' => [$e->getMessage()],
            ]);
        }

        // Localize role display name for the flash message. Use the validated
        // role looked up above (already non-null via findOrFail).
        /** @var array<string, string> $roleNames */
        $roleNames = $role->getTranslations('display_name');
        $roleLabel = $roleNames['en'] ?? $role->key;

        return redirect()->back()->with('success', __('matches.signup.success', ['role' => $roleLabel]));
    }

    public function destroy(
        Request $request,
        GameMatch $match,
        MatchSlot $slot,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        // URL-param mismatch — slot must belong to the named match.
        abort_unless($slot->match_id === $match->id, 404);

        // Ownership — viewer can only cancel their own signup.
        abort_unless($slot->occupant_user_id === $user->id, 403);

        $slot->update([
            'occupant_user_id' => null,
            'confirmed_at' => null,
        ]);

        return redirect()->back()->with('success', __('matches.signup.cancelled'));
    }
}
