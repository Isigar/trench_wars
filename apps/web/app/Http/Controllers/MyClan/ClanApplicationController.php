<?php

declare(strict_types=1);

namespace App\Http\Controllers\MyClan;

use App\Http\Controllers\Controller;
use App\Models\ClanApplication;
use App\Models\User;
use App\Services\ClanApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Source: 02-11-PLAN.md Task 1.
 *
 * Handles application lifecycle actions (accept / decline / cancel).
 *
 * Routes (all require auth middleware):
 *   POST /my-clan/applications/{application}/accept  -> accept  (Leader/Officer)
 *   POST /my-clan/applications/{application}/decline -> decline (Leader/Officer)
 *   POST /applications/{application}/cancel          -> cancel  (Applicant withdraws)
 *
 * The cancel route is NOT under /my-clan because the applicant may not be
 * a member of any clan (they applied from outside).
 */
class ClanApplicationController extends Controller
{
    /**
     * Accept a pending application.
     *
     * The service verifies the acceptor is an active Leader/Officer in the
     * application's target clan (T-02-07-01 mitigation).
     */
    public function accept(Request $request, ClanApplication $application, ClanApplicationService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $service->accept($application, $actor);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages([
                'application' => [$e->getMessage()],
            ]);
        }

        return redirect()->back()->with('success', __('clans.applications.accepted', [
            'name' => $actor->username,
        ]));
    }

    /**
     * Decline a pending application.
     *
     * The service verifies the decliner is an active Leader/Officer in the
     * application's target clan.
     */
    public function decline(Request $request, ClanApplication $application, ClanApplicationService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $service->decline($application, $actor);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages([
                'application' => [$e->getMessage()],
            ]);
        }

        return redirect()->back()->with('success', __('clans.applications.declined'));
    }

    /**
     * Cancel a pending application (applicant withdraws their own application).
     *
     * The service asserts $actor->id === $application->applicant_user_id.
     */
    public function cancel(Request $request, ClanApplication $application, ClanApplicationService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $service->cancel($application, $actor);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages([
                'application' => [$e->getMessage()],
            ]);
        }

        return redirect()->back()->with('success', __('clans.applications.cancelled'));
    }
}
