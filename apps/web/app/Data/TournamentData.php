<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Tournament;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-10-PLAN.md <interfaces>
 *         TournamentData (admin full state) + Phase 4 MatchData canonical idiom.
 *
 * Admin-facing Tournament DTO. Carries the full field surface plus optional
 * nested participants[] / stages[] when the caller eager-loads them.
 *
 * Translatable JSONB fields (title / description) surface as the full locale
 * array — Phase 3 Pitfall 4: emit `getTranslations()`, NOT the active-locale
 * scalar. Empty arrays collapse to null so Vue's `v-if="t.title !== undefined"`
 * contract works.
 *
 * `starts_at` / `ends_at` are emitted as ISO-8601 strings (or null) so the
 * Vue/JS side reads a deterministic shape.
 */
#[TypeScript]
final class TournamentData extends Data
{
    /**
     * @param  array<string, string>|null  $title
     * @param  array<string, string>|null  $description
     * @param  array<string, mixed>|null  $settings
     * @param  list<TournamentParticipantData>|null  $participants
     * @param  list<TournamentStageData>|null  $stages
     */
    public function __construct(
        public string $id,
        public string $game_id,
        public string $slug,
        public ?array $title,
        public ?array $description,
        public string $format,
        public string $status,
        public ?string $starts_at,
        public ?string $ends_at,
        public ?int $max_participants,
        public ?array $settings,
        public string $organiser_user_id,
        public ?string $default_game_match_type_id,
        public bool $is_public,
        public ?array $participants,
        public ?array $stages,
    ) {}

    public static function fromModel(Tournament $tournament): self
    {
        /** @var Carbon|null $startsAt */
        $startsAt = $tournament->starts_at;
        /** @var Carbon|null $endsAt */
        $endsAt = $tournament->ends_at;
        /** @var array<string, mixed>|null $settings */
        $settings = $tournament->settings;

        return new self(
            id: $tournament->id,
            game_id: $tournament->game_id,
            slug: $tournament->slug,
            title: $tournament->getTranslations('title') ?: null,
            description: $tournament->getTranslations('description') ?: null,
            format: $tournament->format,
            status: $tournament->status,
            starts_at: $startsAt?->toIso8601String(),
            ends_at: $endsAt?->toIso8601String(),
            max_participants: $tournament->max_participants,
            settings: $settings,
            organiser_user_id: $tournament->organiser_user_id,
            default_game_match_type_id: $tournament->default_game_match_type_id,
            is_public: $tournament->is_public,
            participants: $tournament->relationLoaded('participants')
                ? array_values($tournament->participants
                    ->map(fn ($participant) => TournamentParticipantData::fromModel($participant))
                    ->all())
                : null,
            stages: $tournament->relationLoaded('stages')
                ? array_values($tournament->stages
                    ->map(fn ($stage) => TournamentStageData::fromModel($stage))
                    ->all())
                : null,
        );
    }
}
