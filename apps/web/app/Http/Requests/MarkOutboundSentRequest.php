<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Source: 05-04-PLAN.md <interfaces> MarkOutboundSentRequest block.
 *
 * Validates POST /api/bot/outbound-messages/{id}/sent. `sent_message_id` is
 * the Discord snowflake returned by Discord's REST API on a successful send;
 * max 64 chars accommodates any future Discord ID-shape expansion (current
 * snowflakes are 17-20 digits).
 *
 * authorize(): always true — Sanctum's abilities:bot:write-outbound is the
 * auth gate.
 */
final class MarkOutboundSentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sent_message_id' => ['required', 'string', 'max:64'],
        ];
    }
}
