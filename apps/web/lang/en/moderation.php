<?php

declare(strict_types=1);

/*
| Source: .planning/phases/09-polish/09-01-PLAN.md task 1 i18n catalogue
| + 09-RESEARCH.md § Moderator Tooling & Disputes.
|
| D-013 — every t()/__() consumed by Phase 9 (UserResource BulkActions,
| MatchResource bulk-cancel modal, MatchDisputeResource state-machine labels,
| activity_log audit copy) resolves to a key here from day one. CI gate:
| Phase9I18nKeyCoverageTest (plan 09-12).
|
| Naming: snake_case, hierarchical groups. Placeholder English copy.
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Filament BulkActions (label + modal heading + modal description + form fields)
    |--------------------------------------------------------------------------
    | Consumed by: UserResource (BulkAction ban + unban), MatchResource (BulkAction
    | match_cancel). Plan 09-07 wires the Filament classes.
    */
    'bulk' => [
        'ban' => [
            'label' => 'Ban selected users',
            'modal_heading' => 'Ban :count user(s)',
            'modal_description' => 'Issues a Ban record + writes activity_log entry. Bans take effect immediately.',
            'ban_type' => 'Ban type',
            'reason' => 'Reason',
            'expires_at' => 'Expires at (optional)',
            'confirm' => 'Ban users',
        ],
        'unban' => [
            'label' => 'Unban selected users',
            'modal_heading' => 'Unban :count user(s)',
            'modal_description' => 'Clears active bans and records an activity_log entry.',
            'confirm' => 'Unban users',
        ],
        'match_cancel' => [
            'label' => 'Cancel selected matches',
            'modal_heading' => 'Cancel :count match(es)',
            'modal_description' => 'Cancels matches and fans out match_cancelled notifications to all signed-up players.',
            'reason' => 'Cancellation reason',
            'confirm' => 'Cancel matches',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ban model — type + status enum labels
    |--------------------------------------------------------------------------
    */
    'ban' => [
        'types' => [
            'temporary' => 'Temporary',
            'permanent' => 'Permanent',
        ],
        'status' => [
            'active' => 'Active',
            'expired' => 'Expired',
            'lifted' => 'Lifted',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MatchDispute state machine labels (Filament status badges)
    |--------------------------------------------------------------------------
    | State transitions: open → under_review → resolved/rejected.
    | Each transition writes an activity_log row via LogsActivity trait.
    */
    'dispute' => [
        'status' => [
            'open' => 'Open',
            'under_review' => 'Under review',
            'resolved' => 'Resolved',
            'rejected' => 'Rejected',
        ],
        'resolution' => [
            'result_amended' => 'Result amended',
            'result_voided' => 'Result voided',
            'no_action' => 'No action taken',
            'sanction_issued' => 'Sanction issued',
        ],
        'fields' => [
            'reason' => 'Reason',
            'evidence' => 'Evidence (links or attachments)',
            'resolution_notes' => 'Resolution notes',
            'resolved_at' => 'Resolved at',
            'resolved_by' => 'Resolved by',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity-log audit subject lines (rendered in admin audit timeline)
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'ban_issued' => ':actor banned :subject (:ban_type, reason: :reason)',
        'ban_lifted' => ':actor lifted ban on :subject',
        'dispute_transitioned' => ':actor moved dispute :dispute_id from :from to :to',
        'match_cancelled_bulk' => ':actor cancelled :count match(es) (reason: :reason)',
        'abuse_report_actioned' => ':actor actioned abuse report :report_id (outcome: :outcome)',
    ],
];
