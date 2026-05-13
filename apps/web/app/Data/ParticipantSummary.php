<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-10-PLAN.md <interfaces> ParticipantSummary +
 *         Phase 4 PublicMatchOccupantData precedent (compact inline DTO).
 *
 * Minimal participant projection used inside BracketNodeData::participant_a /
 * participant_b — only the three fields the SVG bracket renderer (plan 06-12)
 * needs to draw a bracket-node label: id (for keying / linking), clan_name
 * (display), seed (header badge). Per D-018 the clan name is always public.
 *
 * Naming: matches Phase 4 PublicMatchOccupantData precedent for "compact inline
 * DTO carried inside a larger render DTO". The compact shape keeps api.d.ts
 * payload size small for the public polling endpoint.
 */
#[TypeScript]
final class ParticipantSummary extends Data
{
    public function __construct(
        public string $id,
        public string $clan_name,
        public int $seed,
    ) {}
}
