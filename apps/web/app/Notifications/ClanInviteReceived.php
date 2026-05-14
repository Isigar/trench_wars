<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ClanInvite;
use App\Models\User;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 2.
 *
 * Fired when a clan officer issues a ClanInvite (plan 09-07 ClanInviteService).
 * The invited user receives both a database bell entry and (default-on) a
 * Discord DM with the invite-accept/decline CTA.
 *
 * D-04-03-A irrelevant.
 * D-013 / Pitfall 4 LOCKED.
 */
final class ClanInviteReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ClanInvite $invite,
    ) {}

    /** @return array<int, string> */
    public function via(User $notifiable): array
    {
        $channels = $notifiable->enabledNotificationChannels('clan_invite_received');

        return array_map(
            fn (string $key): string => $key === 'discord' ? DiscordChannel::class : $key,
            $channels,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'invite_id' => $this->invite->id,
            'clan_id' => $this->invite->clan_id,
            'inviter_user_id' => $this->invite->inviting_user_id,
            'i18n_key' => 'notifications.clan_invite_received.title',
        ];
    }

    public function databaseType(User $notifiable): string
    {
        return 'clan.invite_received';
    }

    /** @return array<string, mixed> */
    public function toDiscord(User $notifiable): array
    {
        return [
            'message_type' => 'user_dm',
            'channel_id' => '',
            'recipient_id' => (string) $notifiable->discord_id,
            'payload' => [
                'embed_title' => __('notifications.clan_invite_received.title', [
                    'clan' => '—',
                ]),
                'embed_description' => __('notifications.clan_invite_received.body', [
                    'inviter' => '—',
                    'clan' => '—',
                ]),
                'cta_url' => url('/clans'),
                'color_token' => 'info',
            ],
        ];
    }
}
