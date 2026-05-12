<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ClanData;
use App\Data\ClanTagData;
use App\Models\Clan;
use App\Models\ClanTag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Source: .planning/phases/02-clans-tags/02-07-PLAN.md Task 1.
 *
 * Public GET /clans — clan directory page. No auth required.
 * Supports ?tag= and ?q= query parameters for filtering.
 *
 * Security (T-02-04-03, T-02-04-04): Query params are bound via Eloquent —
 * no raw SQL, parameter injection is impossible.
 * Security (T-02-04-06): Paginated at 20 per page to prevent DDoS.
 */
class ClanDirectoryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'tag' => 'nullable|string|alpha_dash|max:32',
            'q' => 'nullable|string|max:100',
        ]);

        $tag = $validated['tag'] ?? null;
        $q = $validated['q'] ?? null;

        $query = Clan::query()
            ->where('status', 'active')
            ->with(['tags', 'activeMembers']);

        // T-02-04-03: tag resolved via Eloquent — no raw SQL.
        if ($tag !== null && $tag !== '') {
            $tagModel = ClanTag::where('slug', $tag)->firstOrFail();
            $query->whereHas('tags', fn ($q) => $q->where('clan_tags.id', $tagModel->id));
        }

        // T-02-04-04: ILIKE via Eloquent binding — no raw SQL injection.
        if ($q !== null && $q !== '') {
            $query->where(
                fn ($query) => $query
                    ->where('name', 'ILIKE', "%{$q}%")
                    ->orWhere('tag', 'ILIKE', "%{$q}%")
            );
        }

        $paginator = $query->paginate(20);

        return Inertia::render('Clans/Index', [
            'clans' => $paginator->getCollection()->map(fn (Clan $clan) => ClanData::fromModel($clan))->values()->all(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'perPage' => $paginator->perPage(),
            ],
            'tags' => ClanTag::all()->map(fn (ClanTag $t) => ClanTagData::fromModel($t))->all(),
            'activeTagSlug' => $tag,
            'activeSearch' => $q,
        ]);
    }
}
