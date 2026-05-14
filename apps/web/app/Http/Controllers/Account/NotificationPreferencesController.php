<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\UserNotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: .planning/phases/09-polish/09-06-PLAN.md task 1.
 *
 * Authenticated-only Inertia GET + POST for the 5×2 (event_type × channel)
 * notification preference matrix.
 *
 * Default policy mirror (User::enabledNotificationChannels — see User model
 * docblock):
 *   - database channel: default-ON for every event_type.
 *   - discord channel:  default-ON IF user has discord_id AND event_type
 *                       is NOT `match_result_published` (Open Question 3).
 *
 * UI presentation (plan 09-06):
 *   The Vue page renders a 5×2 grid of Reka Switch components. The default
 *   policy is materialised into the matrix client-side so the user always
 *   sees the *effective* state of each toggle — pre-existing preference
 *   rows merely override the default.
 *
 * Persistence (T-09-06-03 mitigation):
 *   `updateOrCreate` is keyed by `auth()->id()` AND `event_type` + `channel`
 *   so only the auth user's own preference rows are touched. The composite
 *   `unp_unique` index (plan 09-02 migration 2026_05_18_100100) makes the
 *   updateOrCreate semantics race-free.
 */
class NotificationPreferencesController extends Controller
{
    /** @var array<int, string> */
    private const EVENT_TYPES = [
        'match_starting_soon',
        'match_cancelled',
        'match_result_published',
        'clan_application_decided',
        'clan_invite_received',
    ];

    /** @var array<int, string> */
    private const CHANNELS = [
        'database',
        'discord',
    ];

    /**
     * Render the 5×2 matrix as a nested map `[event_type => [channel => bool]]`.
     * Missing rows fall back to the per-channel default policy so the UI always
     * shows the effective state.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        /** @var array<string, array<string, bool>> $matrix */
        $matrix = [];

        // Pre-load every existing preference row in one query (avoids per-toggle
        // N+1 inside the nested loops below).
        $existing = $user->notificationPreferences()->get()->keyBy(
            fn (UserNotificationPreference $row): string => $row->event_type . ':' . $row->channel,
        );

        $hasDiscord = ! empty($user->discord_id);

        foreach (self::EVENT_TYPES as $eventType) {
            foreach (self::CHANNELS as $channel) {
                $row = $existing->get("{$eventType}:{$channel}");

                if ($row instanceof UserNotificationPreference) {
                    $matrix[$eventType][$channel] = (bool) $row->enabled;

                    continue;
                }

                // Default policy fallback — mirrors User::enabledNotificationChannels.
                // Channels are constrained to self::CHANNELS (validated above) so no
                // `default` arm is needed; PHPStan match.alwaysTrue requires it removed.
                $matrix[$eventType][$channel] = match ($channel) {
                    'database' => true,
                    'discord' => $hasDiscord && $eventType !== 'match_result_published',
                };
            }
        }

        return Inertia::render('Account/NotificationPreferences', [
            'preferences' => $matrix,
            'event_types' => self::EVENT_TYPES,
            'channels' => self::CHANNELS,
        ]);
    }

    /**
     * Persist a diff of the matrix. Payload shape:
     *   {
     *     "preferences": [
     *       {"event_type": "match_starting_soon", "channel": "database", "enabled": true},
     *       ...
     *     ]
     *   }
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $eventTypesRule = 'in:' . implode(',', self::EVENT_TYPES);
        $channelsRule = 'in:' . implode(',', self::CHANNELS);

        $validated = $request->validate([
            'preferences' => ['required', 'array', 'max:32'],
            'preferences.*.event_type' => ['required', 'string', $eventTypesRule],
            'preferences.*.channel' => ['required', 'string', $channelsRule],
            'preferences.*.enabled' => ['required', 'boolean'],
        ]);

        /** @var array<int, array{event_type: string, channel: string, enabled: bool}> $rows */
        $rows = $validated['preferences'];

        DB::transaction(function () use ($user, $rows): void {
            foreach ($rows as $row) {
                UserNotificationPreference::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'event_type' => $row['event_type'],
                        'channel' => $row['channel'],
                    ],
                    ['enabled' => $row['enabled']],
                );
            }
        });

        return back()->with('success', __('notifications.preferences_saved'));
    }
}
