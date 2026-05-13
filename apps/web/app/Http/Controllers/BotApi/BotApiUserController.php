<?php

declare(strict_types=1);

namespace App\Http\Controllers\BotApi;

use App\Data\PublicPlayerData;
use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PlayerPrivacyGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Source: 05-04-PLAN.md <interfaces> BotApiUserController block + 05-RESEARCH.md
 * Open Question Q5 (own-profile bypass for /users/me).
 *
 * GET /api/bot/users/me returns the privacy-aware profile DTO for the human
 * resolved by bot.acts-as middleware. Because the viewer IS the subject, the
 * Phase 2 PlayerPrivacyGate's own-profile bypass grants full field access
 * regardless of the player's tier settings (show_to, show_real_name, ...).
 *
 * The endpoint is the bot's identity-introspection surface — the bot uses it
 * during a slash-command flow to introspect "who am I logged in as on the
 * website right now?" without needing a separate profile lookup.
 *
 * Threat refs:
 *   T-05-04-08 (information disclosure) — own-profile bypass is CORRECT here
 *     (subject == viewer). For /profile (bot-side plan 05-09) on someone else,
 *     the privacy gate applies normally.
 */
final class BotApiUserController extends Controller
{
    public function me(Request $request, PlayerPrivacyGate $gate): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $player = $user->player;

        // Defensive: every User has a Player row (Phase 1 ProvisionFirstLogin).
        // If absent, return user-only payload so the bot still gets identity.
        if ($player === null) {
            return response()->json([
                'user' => UserData::from($user),
                'player' => null,
            ]);
        }

        // Eager-load PlayerPrivacy + active membership for the DTO factory.
        $player->loadMissing(['privacy', 'user']);

        return response()->json([
            'user' => UserData::from($user),
            'player' => PublicPlayerData::fromPlayer($player, $user, $gate),
        ]);
    }
}
