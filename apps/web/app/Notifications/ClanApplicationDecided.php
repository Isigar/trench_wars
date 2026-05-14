<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ClanApplication;
use App\Models\User;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 2.
 *
 * Fired when a clan officer accepts or rejects a pending ClanApplication
 * (plan 09-07 ClanApplicationService transition handler).
 *
 * Both `accepted` and `declined` paths land here; the i18n_key selects the
 * variant on render. The `application->status` carries the discriminator.
 *
 * D-04-03-A irrelevant (no GameMatch).
 * D-013 / Pitfall 4 LOCKED.
 */
final class ClanApplicationDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ClanApplication $application,
    ) {}

    /** @return array<int, string> */
    public function via(User $notifiable): array
    {
        $channels = $notifiable->enabledNotificationChannels('clan_application_decided');

        return array_map(
            fn (string $key): string => $key === 'discord' ? DiscordChannel::class : $key,
            $channels,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'clan_id' => $this->application->clan_id,
            'status' => $this->application->status,
            'i18n_key' => $this->application->status === 'accepted'
                ? 'notifications.clan_application_decided.approved.title'
                : 'notifications.clan_application_decided.rejected.title',
        ];
    }

    public function databaseType(User $notifiable): string
    {
        return 'clan.application_decided';
    }

    /** @return array<string, mixed> */
    public function toDiscord(User $notifiable): array
    {
        $isApproved = $this->application->status === 'accepted';
        $variantKey = $isApproved ? 'approved' : 'rejected';

        return [
            'message_type' => 'user_dm',
            'channel_id' => '',
            'recipient_id' => (string) $notifiable->discord_id,
            'payload' => [
                'embed_title' => __("notifications.clan_application_decided.{$variantKey}.title"),
                'embed_description' => __("notifications.clan_application_decided.{$variantKey}.body", [
                    'clan' => '—',
                    'reason' => '—',
                ]),
                'cta_url' => url('/clans'),
                'color_token' => $isApproved ? 'success' : 'warning',
            ],
        ];
    }
}
