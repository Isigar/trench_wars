<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyClan;

use App\Http\Controllers\Controller;
use App\Http\Requests\MyClan\UpdateClanProfileRequest;
use App\Models\Clan;
use Illuminate\Http\RedirectResponse;

/**
 * Source: 02-09-PLAN.md Task 2.
 *
 * PATCH /my-clan/profile/{clan:slug} — update the clan profile.
 *
 * Authorization is handled by UpdateClanProfileRequest::authorize() via ClanPolicy::update.
 * Mass-assignment safety: $request->validated() returns ONLY the whitelisted keys
 * (name, tag, description, country_code) — discord_role_id is excluded (T-02-05-02).
 */
class MyClanProfileController extends Controller
{
    public function update(UpdateClanProfileRequest $request, Clan $clan): RedirectResponse
    {
        // validated() contains ONLY the fields declared in UpdateClanProfileRequest::rules().
        // discord_role_id cannot land here regardless of what the request body contains.
        $clan->update($request->validated());

        return redirect()->back()->with('success', __('clans.profile.update.success'));
    }
}
