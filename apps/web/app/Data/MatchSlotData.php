<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\MatchSlot;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/04-matches-manual/04-RESEARCH.md § Pattern 1 (match_slots) +
 *         04-07-PLAN.md <interfaces> MatchSlotData block.
 *
 * Capacity-row DTO for the (match, role, slot_index) tuple. Admin-facing — the
 * privacy-shaped public sibling is `PublicMatchOccupantData`. `occupant_user_id`
 * is exposed raw here because the consumer (Filament + my-clan management) needs
 * the FK directly; the public path NEVER receives this raw FK.
 *
 * `confirmed_at` is emitted as ISO-8601 string (or null when the slot is empty
 * or unconfirmed) — Vue's dayjs parses both shapes uniformly.
 */
#[TypeScript]
final class MatchSlotData extends Data
{
    public function __construct(
        public string $id,
        public string $match_id,
        public string $game_role_id,
        public int $slot_index,
        public ?string $occupant_user_id,
        public ?string $confirmed_at,
        public int $sort_order,
    ) {}

    /**
     * Build a MatchSlotData from a MatchSlot Eloquent model.
     */
    public static function fromModel(MatchSlot $slot): self
    {
        /** @var Carbon|null $confirmedAt */
        $confirmedAt = $slot->confirmed_at;

        return new self(
            id: $slot->id,
            match_id: $slot->match_id,
            game_role_id: $slot->game_role_id,
            slot_index: $slot->slot_index,
            occupant_user_id: $slot->occupant_user_id,
            confirmed_at: $confirmedAt?->toIso8601String(),
            sort_order: $slot->sort_order,
        );
    }
}
