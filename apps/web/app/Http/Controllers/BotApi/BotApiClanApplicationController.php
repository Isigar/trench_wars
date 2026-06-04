<?php

declare(strict_types=1);

namespace App\Http\Controllers\BotApi;

use App\Data\ClanApplicationData;
use App\Exceptions\AlreadyInClanException;
use App\Exceptions\ClanNotRecruitingException;
use App\Exceptions\DuplicateApplicationException;
use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\User;
use App\Services\ClanApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Source: 10-03-PLAN.md Task 1 — BotApiClanApplicationController.
 *
 * D-004 enforcement (bot is a thin display layer):
 *   store() delegates EVERY write to ClanApplicationService::apply(). The bot
 *   makes ZERO direct writes to clan_applications — the service holds the sole
 *   production write path.
 *
 * Threat refs:
 *   T-10-03-01 (Spoofing via X-Bot-Acts-As-User) — mitigated upstream by the
 *     abilities:bot:act-as-user + bot.acts-as middleware stack; Auth::user() here
 *     is the rebound human, never the bot service account (T-05-04-01 precedent).
 *   T-10-03-02 (Elevation of Privilege — applying as another user) — controller
 *     uses Auth::user() exclusively; no client-supplied applicant_user_id.
 *   T-10-03-03 (Tampering — bypass eligibility) — all guards live in
 *     ClanApplicationService::apply(); controllers cannot opt out.
 *
 * Exception → HTTP response mapping (422 with i18n-keyed error message):
 *   ClanNotRecruitingException  → clan_not_recruiting  / bot.errors.clan_not_recruiting
 *   AlreadyInClanException      → already_in_clan      / bot.errors.already_in_clan
 *   DuplicateApplicationException → duplicate_application / bot.errors.duplicate_application
 */
final class BotApiClanApplicationController extends Controller
{
    public function store(Clan $clan): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            $application = app(ClanApplicationService::class)->apply($clan, $user);
        } catch (ClanNotRecruitingException) {
            return response()->json([
                'error' => 'clan_not_recruiting',
                'message' => __('bot.errors.clan_not_recruiting'),
            ], 422);
        } catch (AlreadyInClanException) {
            return response()->json([
                'error' => 'already_in_clan',
                'message' => __('bot.errors.already_in_clan'),
            ], 422);
        } catch (DuplicateApplicationException) {
            return response()->json([
                'error' => 'duplicate_application',
                'message' => __('bot.errors.duplicate_application'),
            ], 422);
        }

        return response()->json([
            'data' => ClanApplicationData::fromModel($application),
        ], 201);
    }
}
