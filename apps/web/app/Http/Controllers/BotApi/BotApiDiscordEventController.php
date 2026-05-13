<?php

declare(strict_types=1);

namespace App\Http\Controllers\BotApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleChangeEventRequest;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\DiscordOutboundMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Source: 05-04-PLAN.md <interfaces> BotApiDiscordEventController block +
 * 05-RESEARCH.md Pitfall 10 (echo suppression — bot writes a role-sync outbound
 * row; Discord delivers; Discord then fires guildMemberUpdate; bot relays the
 * event back to web; web reconciles and writes a new outbound row; infinite
 * ping-pong unless we suppress own-echo within a 60s window).
 *
 * Algorithm:
 *   1. Look for a matching sent outbound row with the exact same (user, role,
 *      action) tuple and updated_at within the last 60s.
 *   2. If found -> noop, reason=own_echo. The bot does not need to retry;
 *      this is the design contract.
 *   3. Otherwise reconcile idempotently:
 *      - action=add -> firstOrCreate ClanMembership (the partial unique index
 *        on clan_memberships_one_active prevents duplicate active rows; the
 *        firstOrCreate covers the application-layer idempotency).
 *      - action=remove -> set left_at on the active membership (if any).
 *      - Either branch: if the discord_id or role_id has no User/Clan row,
 *        return noop reason=unmapped.
 *
 * Threat refs:
 *   T-05-04-05 (EoP via forged events) — mitigated by abilities:bot:reconcile
 *     scope + echo-window + idempotent firstOrCreate; activity log captures
 *     the bot causer.
 *   T-05-04-09 (forged discord_id) — RoleChangeEventRequest regex-bounds the
 *     ID shape; unknown ID resolves to no User row -> noop unmapped.
 */
final class BotApiDiscordEventController extends Controller
{
    public function roleChange(RoleChangeEventRequest $request): JsonResponse
    {
        /** @var array{user_discord_id: string, role_discord_id: string, action: string} $data */
        $data = $request->validated();

        // 1. Echo suppression (Pitfall 10) — 60s window from updated_at.
        //    We match the sent outbound row by message_type=role_sync + status=sent
        //    + payload JSON keys for user/role/action.
        $echo = DiscordOutboundMessage::query()
            ->where('message_type', 'role_sync')
            ->where('status', 'sent')
            ->where('updated_at', '>', now()->subSeconds(60))
            ->where('payload->discord_user_id', $data['user_discord_id'])
            ->where('payload->discord_role_id', $data['role_discord_id'])
            ->where('payload->action', $data['action'])
            ->exists();

        if ($echo) {
            return response()->json([
                'action' => 'noop',
                'reason' => 'own_echo',
                'message' => __('bot.errors.echo_suppressed'),
            ]);
        }

        // 2. Unmapped guard — if discord_id or role_id has no User/Clan row, noop.
        $user = User::query()->where('discord_id', $data['user_discord_id'])->first();
        $clan = Clan::query()->where('discord_role_id', $data['role_discord_id'])->first();

        if ($user === null || $clan === null) {
            return response()->json([
                'action' => 'noop',
                'reason' => 'unmapped',
            ]);
        }

        // 3. Idempotent reconcile.
        if ($data['action'] === 'add') {
            ClanMembership::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'clan_id' => $clan->id,
                    'left_at' => null,
                ],
                [
                    'role' => 'member',
                    'joined_at' => now(),
                ],
            );

            return response()->json([
                'action' => 'created',
                'clan_id' => $clan->id,
            ]);
        }

        // action === 'remove'
        $membership = ClanMembership::query()
            ->where('user_id', $user->id)
            ->where('clan_id', $clan->id)
            ->whereNull('left_at')
            ->first();

        if ($membership !== null) {
            $membership->update(['left_at' => now()]);
        }

        return response()->json([
            'action' => 'ended',
        ]);
    }
}
