<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\TournamentParticipant;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-10-PLAN.md <interfaces>
 *         TournamentParticipantData.
 *
 * Admin/public-shared participant DTO. `clan_name` + `clan_slug` are public
 * per D-018 — every viewer sees the clan tag/name on a tournament page.
 *
 * Phase 3 Pitfall 4 mitigation: `clan_name` / `clan_slug` resolve through
 * `relationLoaded('clan')` so an un-eager-loaded model emits null rather than
 * triggering an N+1 inside fromModel.
 */
#[TypeScript]
final class TournamentParticipantData extends Data
{
    public function __construct(
        public string $id,
        public string $tournament_id,
        public string $clan_id,
        public ?int $seed,
        public string $status,
        public ?int $placement,
        public ?string $clan_name,
        public ?string $clan_slug,
    ) {}

    public static function fromModel(TournamentParticipant $participant): self
    {
        $clan = $participant->relationLoaded('clan') ? $participant->clan : null;

        return new self(
            id: $participant->id,
            tournament_id: $participant->tournament_id,
            clan_id: $participant->clan_id,
            seed: $participant->seed,
            status: $participant->status,
            placement: $participant->placement,
            clan_name: $clan?->name,
            clan_slug: $clan?->slug,
        );
    }
}
