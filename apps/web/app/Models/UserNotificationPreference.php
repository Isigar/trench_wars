<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserNotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Source: .planning/phases/09-polish/09-03-PLAN.md task 1 +
 *         09-02 migration 2026_05_18_100100 (table user_notification_preferences).
 *
 * Per-user × event_type × channel preference toggle. One row per
 * (user_id, event_type, channel) tuple — composite UNIQUE `unp_unique`.
 *
 * event_type enum (application-validated; NOT DB CHECK):
 *   match_starting_soon | match_cancelled | match_result_published
 *   | clan_application_decided | clan_invite_received
 *
 * channel enum (application-validated; NOT DB CHECK):
 *   database | discord
 *
 * Default policy (User::enabledNotificationChannels — see User model):
 *   - database channel: default-ON for every event_type.
 *   - discord channel:  default-ON IF user has discord_id AND event_type is NOT
 *                       'match_result_published' (Open Question 3 LOCKED).
 *   - default-OFF for `match_result_published` Discord DM — match-result spam is
 *     opt-in only (RESEARCH Open Question 3).
 *
 * Missing rows fall back to the default policy; explicit `enabled=false` rows
 * always override the default to OFF.
 */
final class UserNotificationPreference extends Model
{
    /** @use HasFactory<UserNotificationPreferenceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'event_type',
        'channel',
        'enabled',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'enabled' => 'bool',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
