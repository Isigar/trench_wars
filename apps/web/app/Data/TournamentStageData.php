<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\TournamentStage;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-10-PLAN.md <interfaces>
 *         TournamentStageData.
 *
 * Admin stage projection. `brackets` is nullable — populated only when the
 * caller eager-loads `brackets` (Phase 3 Pitfall 4 relationLoaded guard).
 *
 * `type` is one of the 6 LOCKED stage types CHECK-defended in the migration:
 *   'elim' | 'winners-bracket' | 'losers-bracket' | 'grand-final' | 'group' | 'swiss-round'
 */
#[TypeScript]
final class TournamentStageData extends Data
{
    /**
     * @param  array<string, mixed>|null  $settings
     * @param  list<TournamentBracketData>|null  $brackets
     */
    public function __construct(
        public string $id,
        public string $tournament_id,
        public string $type,
        public int $ordinal,
        public ?string $name,
        public ?array $settings,
        public ?array $brackets,
    ) {}

    public static function fromModel(TournamentStage $stage): self
    {
        $brackets = null;
        if ($stage->relationLoaded('brackets')) {
            $brackets = array_values($stage->brackets
                ->map(fn ($bracket) => TournamentBracketData::fromModel($bracket))
                ->all());
        }

        /** @var array<string, mixed>|null $settings */
        $settings = $stage->settings;

        return new self(
            id: $stage->id,
            tournament_id: $stage->tournament_id,
            type: $stage->type,
            ordinal: $stage->ordinal,
            name: $stage->name,
            settings: $settings,
            brackets: $brackets,
        );
    }
}
