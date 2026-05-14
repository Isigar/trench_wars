<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\GameMatch;
use App\Models\User;
use App\Notifications\Channels\DiscordChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 2 +
 *         09-RESEARCH.md Pattern 1 (canonical notification class shape).
 *
 * Fired by the dispatcher (plan 09-04) at T-60min and T-15min before a
 * scheduled match. Routes to: database bell + (optionally) Discord DM per the
 * user's preference matrix.
 *
 * D-04-03-A LOCKED — uses `App\Models\GameMatch` directly. NO aliases.
 * D-013 LOCKED — every user-visible string flows through `__()`.
 * Pitfall 4 — `databaseType()` returns a unique discriminator string.
 */
final class MatchStartingSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly GameMatch $match,
        public readonly int $minutesUntilStart,
    ) {}

    /**
     * Pattern 7 delegation: which channels fire is decided by the user's
     * preference matrix (via User::enabledNotificationChannels). The mapping
     * 'database' → 'database' is the Laravel-native key; 'discord' → the
     * custom DiscordChannel class FQN.
     *
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        $channels = $notifiable->enabledNotificationChannels('match_starting_soon');

        return array_map(
            fn (string $key): string => $key === 'discord' ? DiscordChannel::class : $key,
            $channels,
        );
    }

    /**
     * Bell payload — minimal, renderer-friendly. The Vue bell list reads this
     * jsonb blob (plan 09-06) and resolves the i18n_key via t().
     *
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'match_id' => $this->match->id,
            'minutes' => $this->minutesUntilStart,
            'i18n_key' => 'notifications.match_starting_soon.title',
        ];
    }

    /**
     * Stable discriminator — written to `notifications.type` (Pitfall 4 LOCKED:
     * every Notification class MUST return a unique value).
     */
    public function databaseType(User $notifiable): string
    {
        return 'match.starting_soon';
    }

    /**
     * DiscordChannel outbox payload (D-004 / Pitfall 3 LOCKED — this method's
     * return is INSERTED into discord_outbound_messages; no HTTP call leaves
     * the web tier).
     *
     * @return array<string, mixed>
     */
    public function toDiscord(User $notifiable): array
    {
        $title = $this->match->getTranslation('title', $notifiable->locale ?? 'en');

        return [
            'message_type' => 'user_dm',
            'channel_id' => '',  // bot resolves DM channel at dispatch
            'recipient_id' => (string) $notifiable->discord_id,
            'payload' => [
                'embed_title' => __('notifications.match_starting_soon.title', [
                    'min' => $this->minutesUntilStart,
                ]),
                'embed_description' => __('notifications.match_starting_soon.body', [
                    'opponent' => $title,
                    'map' => $this->match->getTranslation('description', $notifiable->locale ?? 'en') ?: '—',
                ]),
                'cta_url' => url('/matches/' . $this->match->id),
                'color_token' => 'info',
            ],
        ];
    }
}
