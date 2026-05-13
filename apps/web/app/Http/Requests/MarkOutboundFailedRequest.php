<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Source: 05-04-PLAN.md <interfaces> MarkOutboundFailedRequest block.
 *
 * Validates POST /api/bot/outbound-messages/{id}/failed. `last_error` is a
 * free-form diagnostic string the bot copies from the Discord API error /
 * exception message; capped at 2000 chars to bound DB row size.
 *
 * authorize(): always true — Sanctum's abilities:bot:write-outbound is the
 * auth gate.
 */
final class MarkOutboundFailedRequest extends FormRequest
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
            'last_error' => ['required', 'string', 'max:2000'],
        ];
    }
}
