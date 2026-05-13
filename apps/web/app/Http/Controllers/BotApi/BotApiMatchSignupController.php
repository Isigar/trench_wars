<?php

declare(strict_types=1);

namespace App\Http\Controllers\BotApi;

use App\Data\MatchSlotData;
use App\Exceptions\AlreadySignedUpException;
use App\Exceptions\CapacityExceededException;
use App\Exceptions\MatchNotOpenException;
use App\Exceptions\TagRestrictedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBotMatchSignupRequest;
use App\Models\GameMatch;
use App\Models\GameRole;
use App\Models\MatchSlot;
use App\Models\User;
use App\Services\MatchSignupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Source: 05-04-PLAN.md <interfaces> BotApiMatchSignupController block + Phase 4
 * plan 04-10 MatchSignupController analog. Phase 4 catch-block ordering precedent
 * (D-04-10-A): status > tag > idempotency > capacity.
 *
 * D-004 enforcement (bot is a thin display layer):
 *   store() delegates EVERY write to MatchSignupService::signup(). The bot
 *   makes ZERO direct writes to match_slots — the service holds the sole
 *   production write path to match_slots.occupant_user_id (D-010 row-locked
 *   capacity).
 *
 * D-04-03-A LOCKED naming: App\Models\GameMatch is imported directly (no
 * alias). The route binding {match} resolves to GameMatch via Laravel's
 * implicit binding.
 *
 * Threat refs:
 *   T-05-04-01 (Spoofing via X-Bot-Acts-As-User) — mitigated upstream by
 *     bot.acts-as middleware (plan 05-03); auth()->user() here is the rebound
 *     human, never the bot service account.
 *   T-05-04-02 (Tampering — bypass MatchSignupService) — mitigated structurally
 *     by calling the service.
 *   T-04-10-04 (capacity race) — delegated to the service's lockForUpdate.
 *
 * Exception → HTTP response mapping (422 with i18n-keyed error message):
 *   MatchNotOpenException     → match_not_open      / bot.errors.match_not_open
 *   TagRestrictedException    → tag_restricted      / bot.errors.tag_restricted
 *   AlreadySignedUpException  → already_signed_up   / bot.errors.already_signed_up
 *   CapacityExceededException → capacity_full       / bot.errors.capacity_full
 */
final class BotApiMatchSignupController extends Controller
{
    public function store(StoreBotMatchSignupRequest $request, GameMatch $match): JsonResponse
    {
        /** @var array{game_role_id: string} $validated */
        $validated = $request->validated();

        /** @var GameRole $gameRole */
        $gameRole = GameRole::query()->findOrFail($validated['game_role_id']);

        /** @var User $user */
        $user = Auth::user();

        try {
            /** @var MatchSlot $slot */
            $slot = app(MatchSignupService::class)->signup($match, $user, $gameRole);
        } catch (MatchNotOpenException) {
            return response()->json([
                'error' => 'match_not_open',
                'message' => __('bot.errors.match_not_open'),
            ], 422);
        } catch (TagRestrictedException) {
            return response()->json([
                'error' => 'tag_restricted',
                'message' => __('bot.errors.tag_restricted'),
            ], 422);
        } catch (AlreadySignedUpException) {
            return response()->json([
                'error' => 'already_signed_up',
                'message' => __('bot.errors.already_signed_up'),
            ], 422);
        } catch (CapacityExceededException) {
            return response()->json([
                'error' => 'capacity_full',
                'message' => __('bot.errors.capacity_full'),
            ], 422);
        }

        return response()->json([
            'slot' => MatchSlotData::fromModel($slot),
        ], 201);
    }

    public function destroy(Request $request, GameMatch $match, GameRole $gameRole): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // D-010 / Pitfall 1 — locked SELECT-then-UPDATE inside a transaction.
        // No service exists for the destroy path in v1 (one call site, simple
        // shape). The lock is on the slot row itself: only the occupant_user's
        // own slot can be cancelled, so concurrent destroys cannot collide.
        $deleted = DB::transaction(function () use ($match, $gameRole, $user): int {
            /** @var MatchSlot|null $slot */
            $slot = MatchSlot::query()
                ->where('match_id', $match->id)
                ->where('game_role_id', $gameRole->id)
                ->where('occupant_user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($slot === null) {
                return 0;
            }

            $slot->update([
                'occupant_user_id' => null,
                'confirmed_at' => null,
            ]);

            return 1;
        });

        if ($deleted === 0) {
            return response()->json([
                'error' => 'not_signed_up',
            ], 404);
        }

        return response()->json([], 204);
    }
}
