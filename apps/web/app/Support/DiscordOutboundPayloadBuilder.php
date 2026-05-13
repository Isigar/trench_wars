<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\GameMatch;
use App\Models\MatchSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Source: .planning/phases/05-discord-bot-v1/05-05-PLAN.md task 1 + 05-RESEARCH.md
 *         § Pattern 5 (outbound delivery) + § Example 3 (embed shape).
 *
 * Stateless helper that owns the canonical JSONB payload shape consumed by
 * the bot worker (plan 05-11). Centralising the shape in one place means the
 * bot-side renderer reads a stable contract — adding/removing payload keys
 * only happens here.
 *
 * Two payload variants:
 *   - buildMatchAnnounce — kind=match_announce_new (initial create; bot POSTs a new message)
 *   - buildMatchUpdate   — kind=match_announce_update + prior_sent_message_id (bot EDITs the
 *                          original message if non-null; idempotent UX — no channel spam on
 *                          status flips)
 *
 * NAMING (D-04-03-A LOCKED): the owner model is `App\Models\GameMatch` (NOT `App\Models\Match`
 * — `match` is a reserved PHP 8 keyword). All imports use the direct symbol; no aliases.
 *
 * Threat refs:
 *   - T-05-05-01 (private match leak): the observer guards is_public BEFORE calling builder;
 *     builder itself is shape-only and trusts its caller.
 *   - T-05-05-04 (DoS via mass updates): builder is cheap (loadMissing + groupBy); the rate
 *     guard lives in the observer's wasChanged('status') gate, not here.
 */
final class DiscordOutboundPayloadBuilder
{
    /**
     * Canonical match-announce payload for a freshly created public match.
     *
     * Eager-loads the relations the slot-summary aggregation needs so the
     * observer's caller doesn't pay an N+1 inside a save() transaction.
     *
     * @return array<string, mixed>
     */
    public static function buildMatchAnnounce(GameMatch $match): array
    {
        $match->loadMissing(['gameMatchType', 'hostClan', 'slots.role']);

        /** @var Carbon|null $scheduledAt */
        $scheduledAt = $match->scheduled_at;

        return [
            'kind' => 'match_announce_new',
            'match_id' => $match->id,
            'status' => $match->status,
            'is_public' => $match->is_public,
            'scheduled_at' => $scheduledAt?->toIso8601String(),
            'host_clan_id' => $match->host_clan_id,
            'host_clan_name' => $match->hostClan?->name,
            'game_match_type_id' => $match->game_match_type_id,
            'game_match_type_key' => $match->gameMatchType?->key,
            'title' => $match->getTranslation('title', 'en'),
            'slot_summary' => self::buildSlotSummary($match),
        ];
    }

    /**
     * Match-update payload — same base shape as the announce, plus the prior sent
     * message id so the bot can EDIT the original Discord message rather than POST
     * a new one. When $priorSentMessageId is null (no prior sent row exists yet),
     * the bot will POST a fresh message.
     *
     * @return array<string, mixed>
     */
    public static function buildMatchUpdate(GameMatch $match, ?string $priorSentMessageId): array
    {
        $base = self::buildMatchAnnounce($match);
        $base['kind'] = 'match_announce_update';
        $base['prior_sent_message_id'] = $priorSentMessageId;

        return $base;
    }

    /**
     * Group slots by game_role_id and produce a stable
     *   [{role_id, role_key, role_display, total, filled}]
     * array. PHPStan L8 wants the array shape, not a Collection — terminal
     * ->values()->all() returns an int-keyed array (sequential after ->values()).
     *
     * @return array<int, array<string, mixed>>
     */
    private static function buildSlotSummary(GameMatch $match): array
    {
        /** @var Collection<int, MatchSlot> $slots */
        $slots = $match->slots;

        /** @var Collection<string, Collection<int, MatchSlot>> $grouped */
        $grouped = $slots->groupBy('game_role_id');

        return $grouped->map(function (Collection $group): array {
            /** @var MatchSlot $first */
            $first = $group->first();

            return [
                'role_id' => $first->game_role_id,
                'role_key' => $first->role?->key,
                'role_display' => $first->role?->getTranslation('display_name', 'en'),
                'total' => $group->count(),
                'filled' => $group->whereNotNull('occupant_user_id')->count(),
            ];
        })->values()->all();
    }
}
