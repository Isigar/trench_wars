<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Clan;
use App\Models\GameMatch;
use App\Models\User;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 2 +
 *         09-RESEARCH.md Open Question 3 LOCKED (match-result Discord DM is
 *         default-OFF — opt-in only).
 *
 * Fired when a match's result is published (plan 09-07 MatchResultService).
 * Routes to: database bell ALWAYS; Discord DM ONLY if the recipient has
 * explicitly opted IN via a UserNotificationPreference row
 * (channel='discord', event_type='match_result_published', enabled=true).
 *
 * The User::enabledNotificationChannels special-case handles the default-off
 * Discord rule — this class just delegates to it.
 *
 * D-04-03-A / D-013 / Pitfall 4 LOCKED.
 */
final class MatchResultPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly GameMatch $match,
        public readonly ?Clan $winnerClan = null,
    ) {}

    /** @return array<int, string> */
    public function via(User $notifiable): array
    {
        $channels = $notifiable->enabledNotificationChannels('match_result_published');

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
            'winner_clan_id' => $this->winnerClan?->id,
            'i18n_key' => 'notifications.match_result_published.title',
        ];
    }

    public function databaseType(User $notifiable): string
    {
        return 'match.result_published';
    }

    /** @return array<string, mixed> */
    public function toDiscord(User $notifiable): array
    {
        $winnerName = $this->winnerClan instanceof Clan
            ? $this->winnerClan->name
            : '—';

        return [
            'message_type' => 'user_dm',
            'channel_id' => '',
            'recipient_id' => (string) $notifiable->discord_id,
            'payload' => [
                'embed_title' => __('notifications.match_result_published.title'),
                'embed_description' => __('notifications.match_result_published.body', [
                    'winner' => $winnerName,
                    'loser' => '—',
                    'score' => '—',
                ]),
                'cta_url' => url('/matches/' . $this->match->id),
                'color_token' => 'success',
            ],
        ];
    }
}
