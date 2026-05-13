<?php

use FilamentTiptapEditor\Actions\EditMediaAction;
use FilamentTiptapEditor\Actions\GridBuilderAction;
use FilamentTiptapEditor\Actions\LinkAction;
use FilamentTiptapEditor\Actions\MediaAction;
use FilamentTiptapEditor\Actions\OEmbedAction;
use FilamentTiptapEditor\Enums\TiptapOutput;

/*
| Source: .planning/phases/07-cms/07-01-PLAN.md task 1 (Tiptap profile pinning).
|
| ────────────────────────────────────────────────────────────────────────────
| Pitfall 10 mitigation — Stored XSS via author-inserted iframe / script /
| oembed nodes. Open Question 8 is LOCKED: markdown v2 is out of scope for v1;
| `tiptap_converter()->asHTML` is the SOLE render path for article body.
|
| The 'default' profile here EXPLICITLY EXCLUDES the iframe-bearing nodes
| `oembed`, `youtube`, and `video`. The `source` tool (raw HTML edit) is also
| excluded to prevent authors from bypassing the allowlist. Phase 7 plan 07-05
| ArticleResource form schema references the pinned 'default' profile from
| THIS file — do NOT widen the allowlist without a paired threat-model entry.
|
| Allowlist (safe nodes only):
|   structural: heading, blockquote, hr, bullet-list, ordered-list, checked-list
|   text:       bold, italic, strike, underline, superscript, subscript,
|               lead, small, color, highlight, align-left/center/right
|   media:      link, media (image upload only), table
|   code:       code (inline), code-block
|
| Explicitly EXCLUDED (iframe / raw-HTML / supply-chain risk):
|   oembed, youtube, video, source, grid-builder, details, blocks
| ────────────────────────────────────────────────────────────────────────────
*/

return [
    'direction' => 'ltr',
    'max_content_width' => '5xl',
    'disable_stylesheet' => false,
    'disable_link_as_button' => false,

    /*
    |--------------------------------------------------------------------------
    | Profiles (Pitfall 10 mitigation — safe-node allowlist)
    |--------------------------------------------------------------------------
    |
    | 'default' profile is the canonical Phase 7 ArticleResource toolbar; it
    | excludes every iframe-bearing or raw-HTML tool to keep stored output
    | safe for SSR rendering on the public side.
    |
    */
    'profiles' => [
        'default' => [
            'heading', 'bullet-list', 'ordered-list', 'blockquote', 'hr', '|',
            'bold', 'italic', 'strike', 'underline', '|',
            'link', 'media', 'table', '|',
            'code', 'code-block',
        ],
        'simple' => ['heading', 'hr', 'bullet-list', 'ordered-list', '|', 'bold', 'italic', '|', 'link', 'media'],
        'minimal' => ['bold', 'italic', 'link', 'bullet-list', 'ordered-list'],
        'none' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    |
    */
    'media_action' => MediaAction::class,
    //    'media_action' => Awcodes\Curator\Actions\MediaAction::class,
    'edit_media_action' => EditMediaAction::class,
    'link_action' => LinkAction::class,
    'grid_builder_action' => GridBuilderAction::class,
    'oembed_action' => OEmbedAction::class,

    /*
    |--------------------------------------------------------------------------
    | Output format
    |--------------------------------------------------------------------------
    |
    | Which output format should be stored in the Database.
    |
    | See: https://tiptap.dev/guide/output
    */
    'output' => TiptapOutput::Html,

    /*
    |--------------------------------------------------------------------------
    | Media Uploader
    |--------------------------------------------------------------------------
    |
    | These options will be passed to the native file uploader modal when
    | inserting media. They follow the same conventions as the
    | Filament Forms FileUpload field.
    |
    | See https://filamentphp.com/docs/3.x/panels/installation#file-upload
    |
    */
    'accepted_file_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'application/pdf'],
    'disk' => 'public',
    'directory' => 'images',
    'visibility' => 'public',
    'preserve_file_names' => false,
    'max_file_size' => 2042,
    'min_file_size' => 0,
    'image_resize_mode' => null,
    'image_crop_aspect_ratio' => null,
    'image_resize_target_width' => null,
    'image_resize_target_height' => null,
    'use_relative_paths' => true,

    /*
    |--------------------------------------------------------------------------
    | Menus
    |--------------------------------------------------------------------------
    |
    */
    'disable_floating_menus' => false,
    'disable_bubble_menus' => false,
    'disable_toolbar_menus' => false,

    // Bubble + floating menu tools mirror the safe-node allowlist (Pitfall 10).
    // 'oembed', 'grid-builder', 'details', 'blocks' explicitly omitted.
    'bubble_menu_tools' => ['bold', 'italic', 'strike', 'underline', 'link'],
    'floating_menu_tools' => ['media', 'table', 'code-block'],

    /*
    |--------------------------------------------------------------------------
    | Extensions
    |--------------------------------------------------------------------------
    |
    */
    'extensions_script' => null,
    'extensions_styles' => null,
    'extensions' => [],

    /*
    |--------------------------------------------------------------------------
    | PresetColors
    |--------------------------------------------------------------------------
    |
    | Possibility to define presets colors in ColorPicker.
    | Only hexadecimal value
    'preset_colors' => [
        'primary' => '#f59e0b',
        //..
    ]
    |
    */
    'preset_colors' => [],

    /*
    |--------------------------------------------------------------------------
    | Protocols
    |--------------------------------------------------------------------------
    |
    | With newer versions of Tiptap, you need to define additional protocols
    | for the link extension. i.e. 'ftp', 'mailto', etc.
    |
    */
    'link_protocols' => [],
];
