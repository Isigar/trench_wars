<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\GameMatch;
use App\Models\User;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 2 +
 *         09-RESEARCH.md notification catalogue.
 *
 * Fired when a scheduled match is cancelled (organiser or moderator action,
 * plan 09-07 MatchService::cancel). Routes to: database bell + Discord DM
 * per preference matrix.
 *
 * D-04-03-A / D-013 / Pitfall 4 LOCKED — see MatchStartingSoon docblock.
 */
final class MatchCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly GameMatch $match,
        public readonly ?string $reason = null,
    ) {}

    /** @return array<int, string> */
    public function via(User $notifiable): array
    {
        $channels = $notifiable->enabledNotificationChannels('match_cancelled');

        return array_map(
            fn (string $key): string => $key === 'discord' ? DiscordChannel::class : $key,
            $channels,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(User $notifiable): array
    {
        return [
            'match_id' => $this->match->id,
            'reason' => $this->reason,
            'i18n_key' => 'notifications.match_cancelled.title',
        ];
    }

    public function databaseType(User $notifiable): string
    {
        return 'match.cancelled';
    }

    /** @return array<string, mixed> */
    public function toDiscord(User $notifiable): array
    {
        $opponent = $this->match->getTranslation('title', $notifiable->locale ?? 'en');

        /** @var Carbon|null $scheduledAt */
        $scheduledAt = $this->match->scheduled_at;

        return [
            'message_type' => 'user_dm',
            'channel_id' => '',
            'recipient_id' => (string) $notifiable->discord_id,
            'payload' => [
                'embed_title' => __('notifications.match_cancelled.title'),
                'embed_description' => __('notifications.match_cancelled.body', [
                    'opponent' => $opponent,
                    'date' => $scheduledAt?->format('Y-m-d H:i') ?? '—',
                    'reason' => $this->reason ?? __('notifications.match_cancelled.title'),
                ]),
                'cta_url' => url('/matches/' . $this->match->id),
                'color_token' => 'danger',
            ],
        ];
    }
}
