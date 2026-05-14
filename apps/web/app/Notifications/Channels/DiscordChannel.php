<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\DiscordOutboundMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 2 +
 *         09-RESEARCH.md Pitfall 3 LOCKED (D-004 compliance) +
 *         CLAUDE.md §8 (bot is the only Discord-facing tier; web NEVER POSTs to
 *         discord.com directly).
 *
 * Custom Laravel notification channel that delivers a `Notification` to a User
 * by ENQUEUEING a row in the `discord_outbound_messages` outbox table. The
 * Phase 5 Discord bot polls that table (DiscordOutboundMessage::scopeDispatchable)
 * and emits the actual gateway/REST call — the web tier never touches the
 * Discord API.
 *
 * This is the architectural choke point that enforces D-004. Pitfall 3 LOCKED
 * — if any future maintainer introduces an `Http::post('discord.com/...')` call
 * to the web tier, DiscordChannelOutboxTest::it_makes_no_outbound_http_calls
 * fails and CI blocks the merge.
 *
 * Contract with Notification classes:
 *   - The Notification MUST declare a `toDiscord($notifiable): array` method
 *     returning the outbox row shape:
 *       [
 *         'message_type'  => 'user_dm',                 // required; CHECK-constrained
 *         'channel_id'    => '',                        // resolved by bot at dispatch
 *         'recipient_id'  => $notifiable->discord_id,   // user snowflake (text)
 *         'payload'       => [                          // jsonb body
 *           'embed_title'       => '...',
 *           'embed_description' => '...',
 *           'cta_url'           => '...',
 *           // additional renderer hints (color_token, etc.) optional
 *         ],
 *       ]
 *
 * If `toDiscord` is missing, throw RuntimeException — fail loud at dispatch
 * time so a misconfigured Notification can't silently swallow Discord delivery.
 *
 * Schema mapping (discord_outbound_messages — Phase 5 + Phase 9 plan 09-02):
 *   - id              uuid auto
 *   - channel_id      text NOT NULL — empty string when DMing a user; bot
 *                                     resolves the DM channel at dispatch time.
 *   - message_type    varchar(32)   — 'user_dm' (CHECK-constrained as of 09-02
 *                                     migration 100500).
 *   - status          varchar(16)   — 'pending' on insert.
 *   - payload         jsonb         — combined recipient snowflake + body.
 *   - attempts        smallint      — 0 on insert.
 *   - causer_user_id  uuid nullable — recipient user id (audit trail; bot may
 *                                     also use it to look up locale fallback).
 *   - timestamps      timestamptz   — auto.
 *
 * NOTE — D-09-03-B: the `payload` jsonb intentionally carries `recipient_id` (the
 * user's Discord snowflake) inside the JSON instead of via a dedicated
 * `recipient_user_discord_id` column. The Phase 5 schema does not declare such
 * a column — channel_id + payload hold all routing information. The bot worker
 * inspects payload.recipient_id for `user_dm` message_type and uses Discord's
 * createDM endpoint to resolve the DM channel at dispatch.
 */
final class DiscordChannel
{
    /**
     * Send the notification by writing one outbox row.
     *
     * @param  mixed  $notifiable  typically User; type-hinted as mixed because
     *                             the Laravel notification dispatcher passes
     *                             whatever the dispatch target is, and we want
     *                             to retain that flexibility.
     *
     * @throws RuntimeException if the Notification lacks toDiscord().
     */
    public function send(mixed $notifiable, Notification $notification): DiscordOutboundMessage
    {
        if (! method_exists($notification, 'toDiscord')) {
            throw new RuntimeException(sprintf(
                'Notification %s must declare toDiscord(): array to be deliverable '
                . 'via DiscordChannel.',
                $notification::class
            ));
        }

        /** @var array<string, mixed> $shape */
        $shape = $notification->toDiscord($notifiable);

        // Pitfall 3 contract — message_type and payload are required; channel_id
        // defaults to '' (bot resolves DM channel at dispatch).
        $messageType = (string) ($shape['message_type'] ?? 'user_dm');
        $channelId = (string) ($shape['channel_id'] ?? '');

        /** @var array<string, mixed> $payload */
        $payload = $shape['payload'] ?? [];

        // Carry recipient snowflake INSIDE payload so the bot worker has every
        // routing input in one jsonb blob (Phase 5 contract — see DiscordOutbound
        // PayloadBuilder idiom; no dedicated recipient column on the table).
        if (isset($shape['recipient_id'])) {
            $payload['recipient_id'] = (string) $shape['recipient_id'];
        }

        // causer_user_id audit trail — only set when notifiable is a User (the
        // typical case); leave NULL for any other notifiable shape (broadcast,
        // anonymous, etc.). We never reach out to discord.com — the bot does
        // (Pitfall 3 LOCKED).
        $causerUserId = null;
        if ($notifiable instanceof User) {
            /** @var string|null $idValue */
            $idValue = $notifiable->getKey();
            $causerUserId = $idValue;
        } elseif ($notifiable instanceof Model) {
            $key = $notifiable->getKey();
            $causerUserId = is_string($key) ? $key : null;
        }

        return DiscordOutboundMessage::create([
            'channel_id' => $channelId,
            'message_type' => $messageType,
            'status' => 'pending',
            'payload' => $payload,
            'attempts' => 0,
            'causer_user_id' => $causerUserId,
        ]);
    }
}
