<?php

declare(strict_types=1);

/*
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1 i18n catalogue
| + 09-RESEARCH.md § Security Hardening — abuse_reports.
|
| D-013 — every t()/__() consumed by the Report Abuse flow (modal trigger,
| form fields, reason-code radios, flash messages, AbuseReportResource Filament
| labels) resolves to a key here from day one. CI gate: Phase9I18nKeyCoverageTest
| (plan 09-12).
|
| Naming: snake_case, hierarchical groups. Placeholder English copy.
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Report submission page / modal chrome
    |--------------------------------------------------------------------------
    | Consumed by: Vue ReportAbuseModal.vue + AbuseReport Filament resource (plan 09-11)
    */
    'page' => [
        'title' => 'Report abuse',
        'description' => 'Report a player, clan, or content for review by moderators.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Form fields (target picker, reason code radios, body textarea)
    |--------------------------------------------------------------------------
    */
    'form' => [
        'target_type' => 'What are you reporting?',
        'target_type_options' => [
            'user' => 'A user',
            'clan' => 'A clan',
            'article' => 'A news article',
            'comment' => 'A comment',
        ],
        'reason_code' => 'Reason',
        'body' => 'Describe the issue',
        'body_placeholder' => 'Provide context, links, or screenshots so moderators can investigate.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reason code enum labels (validated set in AbuseReport model)
    |--------------------------------------------------------------------------
    */
    'reason_codes' => [
        'harassment' => 'Harassment or hate speech',
        'spam' => 'Spam or off-topic content',
        'cheating' => 'Cheating or rule-breaking in matches',
        'inappropriate_content' => 'Inappropriate or NSFW content',
        'other' => 'Other (please describe)',
    ],

    /*
    |--------------------------------------------------------------------------
    | AbuseReport status enum labels
    |--------------------------------------------------------------------------
    */
    'status' => [
        'pending' => 'Pending review',
        'dismissed' => 'Dismissed',
        'actioned' => 'Actioned',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flash + CTA copy
    |--------------------------------------------------------------------------
    */
    'flash' => [
        'submitted' => 'Thank you — your report has been submitted. Moderators will review it shortly.',
        'rate_limited' => 'You have submitted too many reports recently. Please try again later.',
    ],

    'cta' => [
        'submit' => 'Submit report',
        'cancel' => 'Cancel',
    ],
];
