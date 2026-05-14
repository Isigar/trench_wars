<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: .planning/phases/09-polish/09-06-PLAN.md task 1.
 *
 * Authenticated-only Inertia + RedirectResponse endpoints for the in-app
 * notifications hub (SC-1):
 *   - GET  /notifications              — paginated bell list
 *   - POST /notifications/{id}/read    — mark a single notification read
 *   - POST /notifications/read-all     — mark every unread notification read
 *
 * Authorisation model (T-09-06-02 mitigation):
 *   Every query routes through `auth()->user()->notifications()->...` so the
 *   `notifiable_id`/`notifiable_type` filter from Laravel's Notifiable trait
 *   scopes rows to the logged-in user automatically. A request for another
 *   user's notification id therefore yields `null` and we abort 404 — no
 *   `policy/Gate::authorize` indirection needed.
 */
class NotificationsController extends Controller
{
    /**
     * Inertia page with the user's notifications, latest first, paginated 20/page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $notifications = $user->notifications()->latest()->paginate(20);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark a single notification as read.
     *
     * Returns 404 (via abort_if) when:
     *   - the id does not exist
     *   - the id belongs to a different user (auth()->user()->notifications()
     *     scopes the query, so `find` returns null in that case — T-09-06-02)
     */
    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $notification = $user->notifications()->find($id);
        abort_if($notification === null, 404);

        $notification->markAsRead();

        return back();
    }

    /**
     * Mark every unread notification on the user's stack as read.
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $user->unreadNotifications->markAsRead();

        return back();
    }
}
