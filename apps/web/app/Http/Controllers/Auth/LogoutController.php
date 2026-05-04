<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Source: 01-RESEARCH.md Pattern 1 step 6 — POST /auth/logout invalidates the
 * session and regenerates the CSRF token. Per OWASP V3 (anti-fixation), we
 * regenerate after logout so any leaked cookie is unusable.
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
