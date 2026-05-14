<?php

declare(strict_types=1);

/*
| Source: .planning/phases/07-cms/07-01-PLAN.md task 2 — full Phase 7 i18n
| namespace skeleton (must_haves.truths line 47).
| Analog (key-group shape): apps/web/lang/en/tournaments.php (Phase 6 plan 06-01).
|
| Phase 7 CMS public copy + Filament admin labels + service-layer error
| messages. Keys are referenced by:
|   - ArticlePublishWorkflow exceptions    (plan 07-07) → cms.errors.invalid_status_transition / scheduled_at_in_past
|   - Tiptap content sanitiser exceptions  (plan 07-05) → cms.errors.tiptap_unsafe_node (Pitfall 10)
|   - Slug uniqueness check                (plan 07-03) → cms.errors.slug_taken
|   - ArticleResource form + table         (plan 07-05) → cms.fields.*, cms.actions.*, cms.status.*
|   - Public Blog/Index, Blog/Show         (plan 07-10) → cms.status.*, cms.empty.articles, cms.empty.search
|   - CmsI18nKeyCoverageTest               (plan 07-12) → all of the above (Pitfall 10 mitigation)
|
| Parameter interpolation: `:slug`, `:from`, `:to`, `:node`.
| Hardcoded English strings here are authoritative; localisation happens in
| later phases (D-013 — plumbed day one, EN at launch).
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Article lifecycle status (D-012 — drives badges + filters)
    |--------------------------------------------------------------------------
    */
    'status' => [
        'draft' => [
            'label' => 'Draft',
            'badge_class' => 'bg-zinc-200 text-zinc-800',
        ],
        'scheduled' => [
            'label' => 'Scheduled',
            'badge_class' => 'bg-amber-100 text-amber-800',
        ],
        'published' => [
            'label' => 'Published',
            'badge_class' => 'bg-emerald-100 text-emerald-800',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament publication actions (plan 07-05 ArticleResource)
    |--------------------------------------------------------------------------
    */
    'actions' => [
        'publish_now' => [
            'label' => 'Publish now',
            'modal_heading' => 'Publish this article?',
            'success' => 'Article is now live.',
        ],
        'schedule' => [
            'label' => 'Schedule',
            'modal_heading' => 'Schedule this article',
            'success' => 'Article scheduled for publication.',
        ],
        'unpublish' => [
            'label' => 'Unpublish',
            'modal_heading' => 'Unpublish this article?',
            'success' => 'Article returned to draft.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Article form fields (Filament + public)
    |--------------------------------------------------------------------------
    */
    'fields' => [
        'title' => [
            'label' => 'Title',
            'help' => 'Shown in listings and as the page <h1>.',
        ],
        'slug' => [
            'label' => 'Slug',
            'help' => 'URL segment — auto-generated from the title; must be unique.',
        ],
        'excerpt' => [
            'label' => 'Excerpt',
            'help' => 'Short summary used in the index card and the meta description.',
        ],
        'body' => [
            'label' => 'Body',
            'help' => 'Rich text — toolbar restricted to safe nodes only (no iframe/script).',
        ],
        'hero' => [
            'label' => 'Hero image',
            'help' => 'Single image, jpeg/png/webp, max 5 MB.',
        ],
        'category_id' => [
            'label' => 'Category',
            'help' => 'Pick from the configured categories.',
        ],
        'scheduled_at' => [
            'label' => 'Scheduled at',
            'help' => 'Future UTC datetime — the Laravel Scheduler flips the article to Published when this passes.',
        ],
        'published_at' => [
            'label' => 'Published at',
            'help' => 'Read-only — stamped automatically when the article first reaches Published.',
        ],
        'allow_discord_announce' => [
            'label' => 'Announce on Discord',
            'help' => 'Posts a message to the league announcements channel when this article transitions to Published.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Service-layer error messages (translated via __())
    |--------------------------------------------------------------------------
    */
    'errors' => [
        'slug_taken' => 'A different article already uses the slug ":slug".',
        'invalid_status_transition' => 'Cannot transition article from :from to :to.',
        'scheduled_at_in_past' => 'Scheduled publication time must be in the future.',
        'tiptap_unsafe_node' => 'Article body contains a disallowed node (:node) — only safe nodes are accepted.',
        'category_in_use' => 'This category has articles assigned — re-assign or delete the articles first.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Empty-state messages (public + admin)
    |--------------------------------------------------------------------------
    */
    'empty' => [
        'articles' => [
            'label' => 'No articles published yet.',
        ],
        'categories' => [
            'label' => 'No categories configured.',
        ],
        'search' => [
            'label' => 'Nothing matched your query.',
        ],
        'events' => [
            'label' => 'No events scheduled.',
        ],
    ],
];
