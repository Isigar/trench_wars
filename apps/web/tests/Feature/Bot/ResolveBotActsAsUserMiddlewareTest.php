<?php

declare(strict_types=1);

use App\Models\DiscordOutboundMessage;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;

/*
| Source: plan 05-03 task 2 + 05-RESEARCH.md Pattern 1.
| GREEN replacement of the Wave 0 stub (05-01-PLAN.md task 2).
|
| Coverage (SC-5 verification surface — middleware layer):
| - it('rebinds auth scope to the User identified by X-Bot-Acts-As-User header')
| - it('passes through (no auth rebind) when header is absent — Pitfall 7 tolerance')
| - it('returns 422 with bot.errors.acts_as_unknown message when discord_id is unknown')
| - it('attributes activity_log causer to the rebound User, not the token owner')
| - it('does NOT persist a session — Auth::onceUsingId only')
| - it('handles non-numeric discord_id gracefully (returns 422)')
| - it('handles malformed (overly long) discord_id (returns 422 not a stack trace)')
| - it('handles too-short discord_id (returns 422 not a stack trace)')
|
| Test route fixture: registered in beforeEach() with the full bot stack
| (auth:sanctum + abilities:bot:read + bot.acts-as). Distinct path
| `/api/_test/bot-middleware-route` so it cannot collide with plan 05-04
| production `/api/bot/*` routes.
*/

beforeEach(function (): void {
    Route::middleware(['auth:sanctum', 'abilities:bot:read', 'bot.acts-as'])
        ->get('/api/_test/bot-middleware-route', fn () => response()->json([
            'ok' => true,
            'auth_id' => auth()->id(),
        ]));
});

it('rebinds auth scope to the User identified by X-Bot-Acts-As-User header', function (): void {
    $botService = User::factory()->create();
    $human = User::factory()->create(['discord_id' => '123456789012345678']);

    $token = $botService->createToken(
        name: 'bot-prod',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(90),
    );

    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => '123456789012345678',
        'Accept' => 'application/json',
    ])->getJson('/api/_test/bot-middleware-route')
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'auth_id' => $human->id, // rebound — NOT $botService->id
        ]);
});

it('passes through (no auth rebind) when header is absent — Pitfall 7 tolerance', function (): void {
    $botService = User::factory()->create();
    $token = $botService->createToken(
        name: 'bot-prod',
        abilities: ['bot:read'],
        expiresAt: now()->addDays(90),
    );

    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'Accept' => 'application/json',
    ])->getJson('/api/_test/bot-middleware-route')
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'auth_id' => $botService->id, // no rebind — still the token owner
        ]);
});

it('returns 422 with bot.errors.acts_as_unknown message when discord_id is unknown', function (): void {
    $botService = User::factory()->create();
    $token = $botService->createToken(
        name: 'bot-prod',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(90),
    );

    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => '999999999999999999', // not in users table
        'Accept' => 'application/json',
    ])->getJson('/api/_test/bot-middleware-route')
        ->assertStatus(422)
        ->assertExactJson([
            'error' => 'acts_as_user_unknown',
            'message' => __('bot.errors.acts_as_unknown'),
        ]);
});

it('attributes activity_log causer to the rebound User, not the token owner', function (): void {
    $botService = User::factory()->create();
    $human = User::factory()->create(['discord_id' => '123456789012345678']);

    // Side-effect test route — creates a DiscordOutboundMessage as auth()->user(),
    // which fires LogsActivity. The activity_log row's causer_id must be $human, not $botService.
    Route::middleware(['auth:sanctum', 'abilities:bot:read', 'bot.acts-as'])
        ->post('/api/_test/bot-middleware-causer', function () {
            DiscordOutboundMessage::create([
                'channel_id' => '111222333444555666',
                'message_type' => 'generic',
                'status' => 'pending',
                'payload' => ['kind' => 'middleware_causer_test'],
                'attempts' => 0,
                'causer_user_id' => auth()->id(),
            ]);

            return response()->json(['ok' => true]);
        });

    $token = $botService->createToken(
        name: 'bot-prod',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(90),
    );

    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => '123456789012345678',
        'Accept' => 'application/json',
    ])->postJson('/api/_test/bot-middleware-causer')
        ->assertOk();

    // The activity_log row attributing the outbound-message creation must point
    // at the human, not the bot service account. This is the SC-5 mechanical
    // guarantee.
    $activity = Activity::query()
        ->where('subject_type', DiscordOutboundMessage::class)
        ->where('description', 'DiscordOutboundMessage created')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($human->id)
        ->and($activity->causer_id)->not->toBe($botService->id);
});

it('does NOT persist a session — Auth::onceUsingId only', function (): void {
    $botService = User::factory()->create();
    User::factory()->create(['discord_id' => '123456789012345678']);

    $token = $botService->createToken(
        name: 'bot-prod',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(90),
    );

    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => '123456789012345678',
        'Accept' => 'application/json',
    ])->getJson('/api/_test/bot-middleware-route')
        ->assertOk();

    // onceUsingId by contract writes NO session row. A subsequent unauthenticated
    // request (no Authorization, no header) MUST NOT be auth'd as the rebound user.
    $this->getJson('/api/_test/bot-middleware-route')
        ->assertStatus(401);
});

it('handles non-numeric discord_id gracefully (returns 422)', function (): void {
    $botService = User::factory()->create();
    $token = $botService->createToken(
        name: 'bot-prod',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(90),
    );

    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => 'not-a-snowflake',
        'Accept' => 'application/json',
    ])->getJson('/api/_test/bot-middleware-route')
        ->assertStatus(422)
        ->assertJson([
            'error' => 'acts_as_user_unknown',
            'message' => __('bot.errors.acts_as_unknown'),
        ]);
});

it('handles malformed (overly long) discord_id (returns 422 not a stack trace)', function (): void {
    $botService = User::factory()->create();
    $token = $botService->createToken(
        name: 'bot-prod',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(90),
    );

    // 30-digit numeric blob — passes ctype_digit but blows past the 20-char cap.
    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => '123456789012345678901234567890',
        'Accept' => 'application/json',
    ])->getJson('/api/_test/bot-middleware-route')
        ->assertStatus(422)
        ->assertJson([
            'error' => 'acts_as_user_unknown',
            'message' => __('bot.errors.acts_as_unknown'),
        ]);
});

it('handles too-short discord_id (returns 422 not a stack trace)', function (): void {
    $botService = User::factory()->create();
    $token = $botService->createToken(
        name: 'bot-prod',
        abilities: ['bot:read', 'bot:act-as-user'],
        expiresAt: now()->addDays(90),
    );

    // 5-digit value — passes ctype_digit but below 17-char snowflake floor.
    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token->plainTextToken,
        'X-Bot-Acts-As-User' => '12345',
        'Accept' => 'application/json',
    ])->getJson('/api/_test/bot-middleware-route')
        ->assertStatus(422);
});
