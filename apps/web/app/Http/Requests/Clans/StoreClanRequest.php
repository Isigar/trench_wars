<?php

declare(strict_types=1);

namespace App\Http\Requests\Clans;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Source: 02-09-PLAN.md Task 2 — validates POST /clans (clan create).
 *
 * Security: authorize() only checks authentication (any authenticated user may
 * attempt to create a clan). The "one active membership" business rule is
 * enforced in ClanCreateController::__invoke() with a 409 response.
 *
 * Mass-assignment guard: validated() returns ONLY the whitelisted keys.
 * discord_role_id is NOT here — it is managed exclusively via Filament admin.
 */
class StoreClanRequest extends FormRequest
{
    /**
     * Any authenticated user may submit this form.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        /** @var int $tagMin */
        $tagMin = config('clan.tag_min_length', 2);
        /** @var int $tagMax */
        $tagMax = config('clan.tag_max_length', 8);
        /** @var int $descMax */
        $descMax = config('clan.description_max_length', 4000);

        return [
            'name' => ['required', 'string', 'max:255'],
            'tag' => ['required', 'string', "min:{$tagMin}", "max:{$tagMax}", 'regex:/^[A-Za-z0-9_-]+$/'],
            'description' => ['nullable', 'string', "max:{$descMax}"],
            'country_code' => ['nullable', 'string', 'size:2'],
        ];
    }
}
