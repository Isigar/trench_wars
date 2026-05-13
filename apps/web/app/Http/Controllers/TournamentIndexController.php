<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-12-PLAN.md <interfaces>
 *         TournamentIndexController.
 *
 * Public GET /tournaments — directory of visible tournaments (SC-3 first half).
 *
 * Visibility (same idiom as MatchCalendarController):
 *   - is_public = true                                                  (private hidden)
 *   - status IN (registering, seeded, running, completed)               (drafts + cancelled hidden)
 *   - ordered by starts_at DESC; capped at 50 rows (T-06-12-01 mitigation — DoS bound).
 *
 * Privacy: D-018 — clan names + tournament titles + format are public;
 * PublicTournamentData fields are mapped manually here (we don't pump every
 * tournament through the heavy DTO factory — only the lightweight directory shape).
 */
class TournamentIndexController extends Controller
{
    public function __invoke(): Response
    {
        $tournaments = Tournament::query()
            ->where('is_public', true)
            ->whereIn('status', ['registering', 'seeded', 'running', 'completed'])
            ->orderByDesc('starts_at')
            ->limit(50)
            ->get(['id', 'slug', 'title', 'format', 'status', 'starts_at', 'ends_at', 'max_participants', 'game_id']);

        return Inertia::render('Tournaments/Index', [
            'tournaments' => $tournaments->map(function (Tournament $t): array {
                /** @var Carbon|null $startsAt */
                $startsAt = $t->starts_at;
                /** @var Carbon|null $endsAt */
                $endsAt = $t->ends_at;

                return [
                    'id' => $t->id,
                    'slug' => $t->slug,
                    'title' => $t->getTranslations('title') ?: null,
                    'format' => $t->format,
                    'status' => $t->status,
                    'starts_at' => $startsAt?->toIso8601String(),
                    'ends_at' => $endsAt?->toIso8601String(),
                    'max_participants' => $t->max_participants,
                ];
            })->values()->all(),
        ]);
    }
}
