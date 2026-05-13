<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Source: 05-RESEARCH.md Pattern 1 (verbatim handle() body) + Pitfall 7 tolerance.
 *
 * Reads the `X-Bot-Acts-As-User` header (a Discord snowflake) on `/api/bot/*` routes
 * that perform writes on behalf of a human, resolves it to a `User` row via
 * `discord_id`, and rebinds the request-scope auth via `Auth::onceUsingId($user->id)`.
 * `LogsActivity::causer()` then attributes every effect to the human, NOT the bot's
 * Sanctum-token-owning service account. This is the SC-5 mechanical guarantee for
 * Phase 5 — Discord bot v1.
 *
 * Tolerance contract (Pitfall 7): a missing header is NOT an error here. The route
 * grouping in plan 05-04 decides which endpoints REQUIRE the header by composing
 * `abilities:bot:act-as-user` immediately BEFORE this middleware. Outbound ack and
 * discord-events endpoints intentionally tolerate a missing header — they run as the
 * bot service account.
 *
 * Threat refs (plan 05-03 register):
 * - T-05-03-02 (token abilities bypass) — defence-in-depth via abilities middleware
 *   running BEFORE this one in the route stack.
 * - T-05-03-04 (unknown discord_id elevation attempt) — explicit 422 with
 *   `bot.errors.acts_as_unknown` instead of silent pass-through.
 * - T-05-03-05 (malformed header → stack trace) — non-digit / over-length header
 *   values short-circuit to 422 before the DB lookup.
 * - T-05-03-06 (session pollution via login) — `Auth::onceUsingId` is stateless by
 *   contract (no session row written), unlike `Auth::loginUsingId`.
 */
final class ResolveBotActsAsUser
{
    /**
     * Discord snowflake length floor (Twitter snowflake epoch 2010 lower bound).
     */
    private const int SNOWFLAKE_MIN_LENGTH = 17;

    /**
     * Discord snowflake length ceiling (future-proofed; current production max is 19).
     */
    private const int SNOWFLAKE_MAX_LENGTH = 20;

    public function handle(Request $request, Closure $next): Response
    {
        $actsAs = $request->header('X-Bot-Acts-As-User');

        // Pitfall 7 tolerance: read-only / outbound-ack / discord-events endpoints
        // do NOT carry this header — pass through without rebind so the request
        // continues to run as the Sanctum-token-owning bot service account.
        if ($actsAs === null) {
            return $next($request);
        }

        // T-05-03-05: short-circuit obviously malformed headers BEFORE hitting the
        // DB. Discord snowflakes are 17–20 digit decimals; reject anything else.
        // `Request::header()` returns `string|null` for a non-repeating header;
        // the null case is handled above so `$actsAs` is narrowed to `string` here.
        if (! ctype_digit($actsAs)
            || strlen($actsAs) < self::SNOWFLAKE_MIN_LENGTH
            || strlen($actsAs) > self::SNOWFLAKE_MAX_LENGTH
        ) {
            return $this->actsAsUnknown();
        }

        $user = User::query()->where('discord_id', $actsAs)->first();

        // T-05-03-04: unknown discord_id is a 422, never a silent pass-through.
        if ($user === null) {
            return $this->actsAsUnknown();
        }

        // T-05-03-06: rebind auth for the request lifetime WITHOUT writing a
        // session row. On a Sanctum bearer-authenticated /api/bot/* request the
        // active guard is Sanctum's RequestGuard (stateless), so we use setUser()
        // on the active guard rather than SessionGuard::onceUsingId (which only
        // exists on the session-backed 'web' guard and would no-op here anyway —
        // the controller resolves auth()->user() through the default guard).
        //
        // We also call setUser() on the 'web' guard so any LogsActivity hook that
        // resolves the causer via the auth manager's default-guard chain still
        // sees the rebound human (defence-in-depth — Activity::getCauser() falls
        // back to Auth::user() which iterates registered guards in order).
        Auth::setUser($user);
        Auth::guard('web')->setUser($user);

        return $next($request);
    }

    /**
     * Canonical 422 response — matches plan 05-01 i18n contract (`bot.errors.acts_as_unknown`).
     */
    private function actsAsUnknown(): Response
    {
        return response()->json([
            'error' => 'acts_as_user_unknown',
            'message' => __('bot.errors.acts_as_unknown'),
        ], 422);
    }
}
