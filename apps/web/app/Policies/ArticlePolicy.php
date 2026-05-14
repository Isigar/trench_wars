<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

/**
 * Source: 07-04-PLAN.md task 2 + <interfaces> verbatim.
 *
 * Authorization matrix:
 *   - viewAny:  always true — public listing (SC-3 public surface)
 *   - view:     true if article status='published' OR actor has articles.update
 *               (editors can preview drafts)
 *   - create:   actor has articles.create
 *   - update:   actor has articles.update AND (actor.id === article.author_user_id
 *               OR actor has articles.publish — senior editors can override)
 *   - publish:  actor has articles.publish
 *   - delete:   actor has super-admin role (defence-in-depth — articles.delete is
 *               EXPLICITLY OMITTED from cms-editor sync in PermissionSeeder; this
 *               policy ALSO checks role membership so a misconfigured permission
 *               grant cannot escalate delete to a cms-editor)
 *
 * Public surface methods accept ?User to allow anonymous visitors to view
 * published articles via plan 07-09 BlogShowController gates.
 */
final class ArticlePolicy
{
    /**
     * Admins bypass all policy checks (Rule 2 — admin-access grants full CRUD via Filament).
     * Without this, ArticlePolicy::update() would deny admin users without an
     * explicit articles.update permission grant.
     *
     * Uses getPermissionNames() instead of hasPermissionTo() to avoid a DB-level
     * exception when the permissions table is empty (e.g. in tests that skip
     * PermissionSeeder).
     */
    public function before(?User $actor, string $ability): ?bool
    {
        if ($actor !== null && $actor->getPermissionNames()->contains('admin-access')) {
            // Delete remains super-admin-only — let the explicit delete() method decide.
            if ($ability === 'delete') {
                return null;
            }

            return true;
        }

        return null;
    }

    /**
     * Public listing — always allowed (anonymous + authenticated).
     * Controller (plan 07-09) filters status='published' at query time.
     */
    public function viewAny(?User $actor): bool
    {
        return true;
    }

    /**
     * Public detail — published articles are visible to anyone; drafts/scheduled
     * are restricted to editors (anyone with articles.update).
     */
    public function view(?User $actor, Article $article): bool
    {
        if ($article->status === 'published') {
            return true;
        }

        return $actor?->can('articles.update') ?? false;
    }

    public function create(?User $actor): bool
    {
        return $actor?->can('articles.create') ?? false;
    }

    /**
     * Update gate — junior editors can edit ONLY their own drafts; senior editors
     * with articles.publish may override (T-07-04-02 mitigation: prevents
     * cross-author overwrites within the cms-editor cohort).
     */
    public function update(?User $actor, Article $article): bool
    {
        if ($actor === null) {
            return false;
        }

        if (! $actor->can('articles.update')) {
            return false;
        }

        return $actor->id === $article->author_user_id || $actor->can('articles.publish');
    }

    public function publish(?User $actor, Article $article): bool
    {
        return $actor?->can('articles.publish') ?? false;
    }

    /**
     * Delete is super-admin only — defence-in-depth complement to
     * PermissionSeeder's explicit omission of articles.delete from cms-editor.
     * T-07-04-01 mitigation.
     */
    public function delete(?User $actor, Article $article): bool
    {
        return $actor?->hasRole('super-admin') ?? false;
    }
}
