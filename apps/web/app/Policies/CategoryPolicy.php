<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Source: 07-04-PLAN.md task 2.
 *
 * Authorization matrix:
 *   - viewAny / view:           always true (public surface — Category names
 *                               appear on public article listings + nav)
 *   - create / update / delete: actor has categories.manage permission
 *
 * Categories are operator-managed; cms-editor gets categories.manage so the
 * editorial team can spin up new content sections without a super-admin in
 * the loop.
 */
final class CategoryPolicy
{
    /**
     * Admins bypass all policy checks. Mirrors ClanPolicy::before() pattern.
     */
    public function before(?User $actor, string $ability): ?bool
    {
        if ($actor !== null && $actor->getPermissionNames()->contains('admin-access')) {
            return true;
        }

        return null;
    }

    public function viewAny(?User $actor): bool
    {
        return true;
    }

    public function view(?User $actor, Category $category): bool
    {
        return true;
    }

    public function create(?User $actor): bool
    {
        return $actor?->can('categories.manage') ?? false;
    }

    public function update(?User $actor, Category $category): bool
    {
        return $actor?->can('categories.manage') ?? false;
    }

    public function delete(?User $actor, Category $category): bool
    {
        return $actor?->can('categories.manage') ?? false;
    }
}
