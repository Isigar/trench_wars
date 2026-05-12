<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReservedSlugException;
use App\Models\Clan;
use Illuminate\Support\Str;

/*
| Source: 02-06-PLAN.md Task 1 + RESEARCH.md Pattern 5 (collision-aware slug algorithm).
|
| Stateless service — auto-resolved by the Laravel container.
| Reserved-slug list lives in config/clan.php (T-02-06-01 mitigation).
*/

final class ClanSlugGenerator
{
    /**
     * Generate a collision-free slug from a clan name.
     *
     * Algorithm:
     *   1. Derive base slug via Str::slug($name).
     *   2. Reject if the base slug is in the reserved list.
     *   3. Append -2, -3, … until no existing Clan row has that slug.
     *
     * @throws \InvalidArgumentException When the name produces an empty slug.
     * @throws ReservedSlugException When the derived slug is reserved.
     */
    public function generate(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            throw new \InvalidArgumentException('Cannot derive slug from name (empty after slug).');
        }

        if ($this->isReserved($base)) {
            throw new ReservedSlugException("Slug '{$base}' is reserved.");
        }

        $slug = $base;
        $i = 2;

        while (Clan::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * Check whether the given slug is in the reserved list.
     *
     * Uses config('clan.reserved_slugs') so the list can be edited without
     * touching service code (T-02-06-01).
     */
    public function isReserved(string $slug): bool
    {
        /** @var list<string> $reserved */
        $reserved = config('clan.reserved_slugs', []);

        return in_array($slug, $reserved, strict: true);
    }
}
