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
        'role' => [
            'update' => [
                'success' => 'Member role updated.',
            ],
        ],
        'remove' => [
            'success' => 'Member removed from the clan.',
        ],
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

    'profile' => [
        'update' => [
            'success' => 'Clan profile updated.',
        ],
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
            'errors' => [
                'reserved' => 'That name is reserved and cannot be used as a clan name.',
            ],
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
        'success' => 'Your clan has been created.',
        'already_member' => 'You are already a member of a clan. Leave your current clan before creating a new one.',
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
        'revoked' => 'Invite revoked.',
        'accepted' => 'You have joined the clan.',
        'declined' => 'Invite declined.',
        'error' => [
            'already_in_clan' => 'This player is already a member of a clan.',
            'not_pending' => 'This invite is no longer pending.',
            'duplicate_invite' => 'This player already has a pending invite to this clan.',
            'invitee_in_clan' => 'You are already a member of a clan.',
        ],
    ],

    'applications' => [
        'empty' => 'No pending applications. Members can apply to join from the clan directory.',
        'accept' => 'Accept',
        'decline' => 'Decline',
        'cancel' => 'Cancel application',
        'accepted' => 'Application accepted. :name has joined the clan.',
        'declined' => 'Application declined.',
        'cancelled' => 'Your application has been cancelled.',
        'error' => [
            'not_pending' => 'This application is no longer pending.',
            'already_in_clan' => 'The applicant is already a member of a clan.',
        ],
    ],

    'actions' => [
        'cancel' => 'Cancel',
    ],
];
