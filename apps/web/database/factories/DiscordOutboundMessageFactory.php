<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordOutboundMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Source: .planning/phases/05-discord-bot-v1/05-02-PLAN.md task 2 <interfaces> block.
 *
 * Replaces the Wave 0 RED stub (commit 242e78f). Default state is a `pending` row
 * with a placeholder payload — state helpers below sharpen the row for specific
 * test scenarios (dispatching, sent, failed, role_sync).
 *
 * The previous Wave 0 stub used a string FQN $model + per-line phpstan-ignore
 * annotations; those are removed now that App\Models\DiscordOutboundMessage exists.
 *
 * @extends Factory<DiscordOutboundMessage>
 */
class DiscordOutboundMessageFactory extends Factory
{
    protected $model = DiscordOutboundMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_id' => (string) fake()->numberBetween(10_000_000_000_000_000, 99_999_999_999_999_999),
            'message_type' => 'match_announce',
            'status' => 'pending',
            'payload' => ['kind' => 'placeholder'],
            'attempts' => 0,
            'last_error' => null,
            'sent_message_id' => null,
            'causer_user_id' => null,
            'backoff_until' => null,
        ];
    }

    /**
     * Explicit pending state (matches the default — provided for test readability).
     */
    public function pending(): self
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    /**
     * Row claimed by the bot — status=dispatching with attempt counter bumped.
     */
    public function dispatching(): self
    {
        return $this->state(fn () => ['status' => 'dispatching', 'attempts' => 1]);
    }

    /**
     * Row delivered — status=sent + sent_message_id populated.
     */
    public function sent(?string $messageId = null): self
    {
        return $this->state(fn () => [
            'status' => 'sent',
            'sent_message_id' => $messageId ?? (string) fake()->numberBetween(10_000_000_000_000_000, 99_999_999_999_999_999),
        ]);
    }

    /**
     * Row failed — status=failed + last_error populated.
     */
    public function failed(string $error = 'Discord 500 — Internal Server Error'): self
    {
        return $this->state(fn () => ['status' => 'failed', 'last_error' => $error]);
    }

    /**
     * Variant: role_sync payload (plan 05-06 SyncDiscordRolesJob writes these).
     */
    public function roleSync(): self
    {
        return $this->state(fn () => [
            'message_type' => 'role_sync',
            'payload' => [
                'discord_user_id' => (string) fake()->numberBetween(10_000_000_000_000_000, 99_999_999_999_999_999),
                'discord_role_id' => (string) fake()->numberBetween(10_000_000_000_000_000, 99_999_999_999_999_999),
                'action' => 'add',
            ],
        ]);
    }
}
