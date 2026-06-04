<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clans;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\User;
use App\Services\ClanApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Source: 10-03-PLAN.md Task 2 — ClanApplyController.
 *
 * D-004 enforcement: all eligibility guards live in ClanApplicationService::apply().
 * This controller is a thin surface that delegates, maps DomainException subclasses
 * to a ValidationException on the 'application' key, and redirects.
 *
 * Catching the base \DomainException covers all three typed subclasses
 * (ClanNotRecruitingException / AlreadyInClanException / DuplicateApplicationException)
 * without enumerating them on the web surface — their i18n messages are already
 * embedded in the exception (set by the service).
 *
 * Threat refs:
 *   T-10-03-02 — controller uses $request->user() (session); no client-supplied applicant.
 *   T-10-03-03 — all guards delegate to ClanApplicationService::apply().
 */
class ClanApplyController extends Controller
{
    public function store(Request $request, Clan $clan, ClanApplicationService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $message = $request->string('message')->trim()->value();
        $message = $message === '' ? null : $message;

        try {
            $service->apply($clan, $actor, $message);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages([
                'application' => [$e->getMessage()],
            ]);
        }

        return redirect()->back()->with('success', __('clans.applications.applied'));
    }
}
