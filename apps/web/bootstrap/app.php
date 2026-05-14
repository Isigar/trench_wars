<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveBotActsAsUser;
use App\Http\Middleware\VerifyRconSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        // Plan 05-03: Sanctum CheckAbilities + ResolveBotActsAsUser aliases for /api/bot/*.
        // 'abilities' = AND-all required scopes; 'ability' = OR-any (kept for future use).
        // 'bot.acts-as' rebinds the request-scope auth via Auth::onceUsingId so
        // LogsActivity attributes the human causer behind each Discord-side action
        // (RESEARCH Pattern 1; SC-5).
        // Plan 08-05: HMAC signature gate for worker→web internal channel.
        // Verifies (X-Rcon-Timestamp, X-Rcon-Nonce, X-Rcon-Signature) triple,
        // 60s freshness window (abs() — Pitfall 2), and Redis-SETNX nonce
        // single-use within a 120s TTL (T-08-05-01). Mounted by plan 08-06
        // on internal RCON ingest routes.
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'bot.acts-as' => ResolveBotActsAsUser::class,
            'rcon.signature' => VerifyRconSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
