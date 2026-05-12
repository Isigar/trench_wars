<?php

declare(strict_types=1);

/*
| Source: 02-UI-SPEC.md § Copywriting Contract — clans namespace strings.
|
| All keys used by Vue pages (via t()) and Blade (via __()) for clan-related surfaces:
| - /clans (directory + filter)
| - /clans/{slug} (clan detail, members section, activity placeholder, privacy notices)
| - /my-clan (tabs, forms, invites, applications, member management)
*/

return [
    'directory' => [
        'title' => 'Clans',
        'subtitle' => 'Browse and discover league clans.',
        'search_placeholder' => 'Search clans…',
        'empty_results' => 'No clans match your search. Try different keywords or clear the filters.',
        'empty_default' => 'No clans have been created yet.',
    ],

    'filter' => [
        'clear' => 'Clear filters',
        'tag_label' => 'Filter by tag',
    ],

    'members' => [
        'count_one' => '1 member',
        'count_other' => ':count members',
        'show_all' => 'Show all :count members',
        'invite_button' => 'Invite member',
        'leader_transfer_warning' => 'You are about to transfer leadership of this clan. This cannot be undone without admin action.',
        'remove_confirm' => 'Remove :name from the clan?',
        'remove_yes' => 'Yes, remove',
    ],

    'section' => [
        'members' => 'Members',
        'recent_activity' => 'Recent activity',
    ],

    'activity' => [
        'placeholder' => 'Match history will appear here once this clan plays their first match.',
    ],

    'privacy' => [
        'roster_hidden_partial' => 'Some members have private profiles.',
        'roster_hidden_all' => 'This clan\'s member list is private.',
    ],

    'my_clan' => [
        'title' => 'Manage your clan',
        'tab' => [
            'profile' => 'Profile',
            'members' => 'Members',
            'invites' => 'Invites',
            'applications' => 'Applications',
        ],
    ],

    'form' => [
        'name' => [
            'label' => 'Clan name',
        ],
        'tag' => [
            'label' => 'Clan tag',
            'hint' => 'Short identifier for your clan, e.g. 91st (2–8 characters).',
        ],
        'description' => [
            'label' => 'Description',
        ],
        'country' => [
            'label' => 'Country',
        ],
        'tags' => [
            'label' => 'Tags',
        ],
        'save' => 'Save changes',
    ],

    'create' => [
        'cta' => 'Create your clan',
    ],

    'no_clan' => [
        'title' => 'You\'re not in a clan',
        'body' => 'Join an existing clan from the directory or create your own.',
        'browse' => 'Browse clans',
    ],

    'invites' => [
        'empty' => 'No pending invites. Invite members from the Members tab.',
        'modal_title' => 'Invite a member',
        'search_label' => 'Search by username',
        'message_label' => 'Message (optional)',
        'message_placeholder' => 'Add a personal note…',
        'send' => 'Send invite',
        'sent' => 'Invite sent.',
        'revoke' => 'Revoke',
        'error' => [
            'already_in_clan' => 'This player is already a member of a clan.',
        ],
    ],

    'applications' => [
        'empty' => 'No pending applications. Members can apply to join from the clan directory.',
        'accept' => 'Accept',
        'decline' => 'Decline',
        'accepted' => 'Application accepted. :name has joined the clan.',
        'declined' => 'Application declined.',
    ],

    'actions' => [
        'cancel' => 'Cancel',
    ],
];
