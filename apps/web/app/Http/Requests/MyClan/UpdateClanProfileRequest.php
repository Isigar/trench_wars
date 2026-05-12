<?php

declare(strict_types=1);

namespace App\Http\Requests\MyClan;

use App\Models\Clan;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Source: 02-09-PLAN.md Task 2 — validates PATCH /my-clan/profile/{clan:slug}.
 *
 * Security (T-02-05-02 mitigation):
 *   - authorize() delegates to ClanPolicy::update — requires active Leader/Officer.
 *   - rules() uses 'sometimes' (PATCH semantics) and excludes discord_role_id.
 *   - validated() will NEVER contain discord_role_id — it is not in rules().
 */
class UpdateClanProfileRequest extends FormRequest
{
    /**
     * Delegate to ClanPolicy::update via the Gate.
     *
     * The route parameter 'clan' is bound via slug (Clan::getRouteKeyName() = 'slug').
     */
    public function authorize(): bool
    {
        /** @var Clan $clan */
        $clan = $this->route('clan');

        return $this->user()?->can('update', $clan) ?? false;
    }

    /**
     * PATCH semantics: 'sometimes' means the rule only applies when the field
     * is present in the request. Absent fields are not validated (and not
     * returned by validated()).
     *
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'tag' => ['sometimes', 'required', 'string', "min:{$tagMin}", "max:{$tagMax}", 'regex:/^[A-Za-z0-9_-]+$/'],
            'description' => ['sometimes', 'nullable', 'string', "max:{$descMax}"],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
        ];
    }
}
