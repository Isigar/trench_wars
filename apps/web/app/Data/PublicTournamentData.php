<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Tournament;
use App\Models\TournamentBracket;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-10-PLAN.md <interfaces>
 *         PublicTournamentData + 06-RESEARCH.md § Pattern 8 (SVG bracket renderer).
 *
 * Visitor-safe tournament projection consumed by the public /tournaments/{slug}
 * 5-tab Vue page (plan 06-12) AND the JSON polling endpoint that drives the
 * SVG bracket renderer.
 *
 * Two composition responsibilities:
 *   1. nodes[]  — one BracketNodeData per TournamentBracket (across all stages)
 *   2. edges[]  — one BracketEdgeData per non-null advances_to_bracket_id
 *                 ('winner' type) AND one per non-null loser_advances_to_bracket_id
 *                 ('loser' type). For an 8-clan single-elim:
 *                 7 brackets → 7 nodes, 6 winner edges (the final has no advance),
 *                 0 loser edges. For a 4-clan double-elim (3+3+1 brackets):
 *                 7 nodes, 5 winner edges + 2 loser edges.
 *
 * Privacy: D-018 — clan names + standings are public; the future MVP fields
 * (Phase 9) will pass through PlayerPrivacyGate. v1 has no PII on this shape.
 *
 * Etag: sha1 over (tournament.updated_at | bracket-id:bracket-updated-at sorted).
 * Deterministic — two calls in the same wall-clock instant emit identical etags
 * so the JSON polling endpoint can short-circuit unchanged responses
 * (304 / If-None-Match).
 */
#[TypeScript]
final class PublicTournamentData extends Data
{
    /**
     * @param  array<string, string>|null  $title
     * @param  array<string, string>|null  $description
     * @param  list<BracketNodeData>  $nodes
     * @param  list<BracketEdgeData>  $edges
     * @param  list<TournamentStandingData>|null  $standings
     * @param  list<TournamentParticipantData>|null  $participants
     */
    public function __construct(
        public string $id,
        public string $slug,
        public ?array $title,
        public ?array $description,
        public string $format,
        public string $status,
        public ?string $starts_at,
        public ?string $ends_at,
        public ?int $max_participants,
        public int $participant_count,
        public array $nodes,
        public array $edges,
        public ?array $standings,
        public ?array $participants,
        public string $etag,
        public string $last_modified_at,
    ) {}

    public static function fromModel(Tournament $tournament): self
    {
        [$nodes, $edges] = self::composeNodesAndEdges($tournament);

        /** @var Carbon|null $startsAt */
        $startsAt = $tournament->starts_at;
        /** @var Carbon|null $endsAt */
        $endsAt = $tournament->ends_at;
        /** @var Carbon $lastModifiedAt */
        $lastModifiedAt = $tournament->updated_at ?? now();

        // participant_count: active + withdrawn + disqualified (A5 LOCKED — past
        // participation retained); excludes 'registered' (not yet seeded).
        $participantCount = $tournament->relationLoaded('participants')
            ? $tournament->participants->where('status', '!=', 'registered')->count()
            : $tournament->participants()->where('status', '!=', 'registered')->count();

        $standings = null;
        if ($tournament->relationLoaded('standings')) {
            $standings = array_values($tournament->standings
                ->map(fn ($standing) => TournamentStandingData::fromModel($standing))
                ->all());
        }

        $participants = null;
        if ($tournament->relationLoaded('participants')) {
            $participants = array_values($tournament->participants
                ->map(fn ($participant) => TournamentParticipantData::fromModel($participant))
                ->all());
        }

        return new self(
            id: $tournament->id,
            slug: $tournament->slug,
            title: $tournament->getTranslations('title') ?: null,
            description: $tournament->getTranslations('description') ?: null,
            format: $tournament->format,
            status: $tournament->status,
            starts_at: $startsAt?->toIso8601String(),
            ends_at: $endsAt?->toIso8601String(),
            max_participants: $tournament->max_participants,
            participant_count: $participantCount,
            nodes: $nodes,
            edges: $edges,
            standings: $standings,
            participants: $participants,
            etag: self::computeEtag($tournament),
            last_modified_at: $lastModifiedAt->toIso8601String(),
        );
    }

    /**
     * Walk stages.brackets to build the (nodes[], edges[]) pair. Caller must
     * eager-load `stages.brackets.participantA.clan`, `stages.brackets.participantB.clan`,
     * and `stages.brackets.match` for N+1-free hydration.
     *
     * @return array{0: list<BracketNodeData>, 1: list<BracketEdgeData>}
     */
    private static function composeNodesAndEdges(Tournament $tournament): array
    {
        /** @var list<BracketNodeData> $nodes */
        $nodes = [];
        /** @var list<BracketEdgeData> $edges */
        $edges = [];

        $stages = $tournament->relationLoaded('stages')
            ? $tournament->stages
            : $tournament->stages()->with(['brackets.participantA.clan', 'brackets.participantB.clan', 'brackets.match'])->get();

        foreach ($stages as $stage) {
            /** @var iterable<TournamentBracket> $brackets */
            $brackets = $stage->relationLoaded('brackets')
                ? $stage->brackets
                : $stage->brackets()->with(['participantA.clan', 'participantB.clan', 'match'])->get();

            foreach ($brackets as $bracket) {
                $nodes[] = BracketNodeData::fromModel($bracket);

                // Odd position → 'a' slot; even position → 'b' slot. This is the
                // ceil(p/2) sibling pairing inverted: positions 1,2 both feed
                // round-2 position 1, with position 1 landing in slot 'a' and
                // position 2 landing in slot 'b'.
                $toSlot = ($bracket->position % 2 === 1) ? 'a' : 'b';

                if ($bracket->advances_to_bracket_id !== null) {
                    $edges[] = new BracketEdgeData(
                        from_bracket_id: $bracket->id,
                        to_bracket_id: $bracket->advances_to_bracket_id,
                        to_slot: $toSlot,
                        type: 'winner',
                    );
                }

                if ($bracket->loser_advances_to_bracket_id !== null) {
                    $edges[] = new BracketEdgeData(
                        from_bracket_id: $bracket->id,
                        to_bracket_id: $bracket->loser_advances_to_bracket_id,
                        to_slot: $toSlot,
                        type: 'loser',
                    );
                }
            }
        }

        return [$nodes, $edges];
    }

    /**
     * Deterministic etag over tournament-level updated_at + every bracket's
     * (id, updated_at). Sorted by bracket id so the input order is stable
     * regardless of eager-load ordering.
     *
     * Phase 9 polish — extend to include standings if/when the public Standings
     * tab adds an If-None-Match short-circuit.
     */
    private static function computeEtag(Tournament $tournament): string
    {
        $bracketTokens = [];

        $stages = $tournament->relationLoaded('stages')
            ? $tournament->stages
            : $tournament->stages()->with('brackets')->get();

        foreach ($stages as $stage) {
            $brackets = $stage->relationLoaded('brackets')
                ? $stage->brackets
                : $stage->brackets()->get();

            foreach ($brackets as $bracket) {
                /** @var Carbon|null $bracketUpdated */
                $bracketUpdated = $bracket->updated_at;
                $bracketTokens[] = $bracket->id . ':' . ($bracketUpdated?->toIso8601String() ?? 'null');
            }
        }

        sort($bracketTokens);

        /** @var Carbon|null $tournamentUpdated */
        $tournamentUpdated = $tournament->updated_at;
        $source = ($tournamentUpdated?->toIso8601String() ?? 'null') . '|' . implode(',', $bracketTokens);

        return sha1($source);
    }
}
