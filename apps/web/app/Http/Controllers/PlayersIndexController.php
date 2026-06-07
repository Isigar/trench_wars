<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PlayerSummaryData;
use App\Models\Player;
use App\Models\User;
use App\Services\PlayerPrivacyGate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public GET /players — player directory index. No auth required.
 *
 * Closes the reachability gap where the header nav and sitemap both linked
 * /players but no such route existed (404). Mirrors ClanDirectoryController:
 * optional ?q= name search + pagination.
 *
 * Privacy (D-018): each player is filtered through PlayerPrivacyGate::canShowInSearch
 * — the same gate the search surface uses — so a private/community/clan-tier player
 * is not listed to a viewer who shouldn't see them (own-profile always passes).
 * Filtering happens on the materialised collection (league-scale player counts),
 * then in-memory pagination produces a stable page window.
 */
final class PlayersIndexController extends Controller
{
    private const PER_PAGE = 24;

    private const MAX_PLAYERS = 1000;

    public function __invoke(Request $request, PlayerPrivacyGate $gate): Response
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        /** @var User|null $viewer */
        $viewer = $request->user();
        $q = $validated['q'] ?? null;
        $page = (int) ($validated['page'] ?? 1);

        $query = Player::query()
            ->with(['privacy', 'user'])
            ->orderBy('display_name');

        // ILIKE via Eloquent binding — no raw SQL injection.
        if ($q !== null && $q !== '') {
            $query->where('display_name', 'ILIKE', "%{$q}%");
        }

        // Privacy-gate filter (D-018) — mirrors SearchService::players.
        $visible = $query
            ->limit(self::MAX_PLAYERS)
            ->get()
            ->filter(fn (Player $p): bool => $gate->canShowInSearch($p, $viewer))
            ->values();

        $total = $visible->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $lastPage);

        $pageItems = $visible
            ->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)
            ->map(fn (Player $p) => PlayerSummaryData::fromModel($p))
            ->values()
            ->all();

        return Inertia::render('Players/Index', [
            'players' => $pageItems,
            'pagination' => [
                'currentPage' => $page,
                'lastPage' => $lastPage,
                'total' => $total,
                'perPage' => self::PER_PAGE,
            ],
            'activeSearch' => $q,
        ]);
    }
}
