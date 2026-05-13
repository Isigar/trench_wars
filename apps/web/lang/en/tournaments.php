<?php

declare(strict_types=1);

/*
| Source: .planning/phases/06-tournaments-brackets/06-01-PLAN.md <interfaces> i18n skeleton.
| Analog (key-group shape): apps/web/lang/en/matches.php (Phase 4 plan 04-01).
|
| Phase 6 tournaments + brackets public copy + service-layer error messages +
| Filament admin action labels. Keys are referenced by:
|   - TournamentStatusService exceptions   (plan 06-04) → tournaments.errors.invalid_transition
|   - TournamentSeedingService exceptions  (plan 06-05) → tournaments.errors.reseed_not_allowed
|   - Bracket generators                   (plan 06-06/06-07) → tournaments.errors.swiss_too_few_participants, brackets_already_generated
|   - BracketAdvancementService exceptions (plan 06-08) → tournaments.errors.winner_not_participant, no_self_advance
|   - TournamentResource + 9 actions       (plan 06-11) → tournaments.actions.*
|   - Public Show.vue / Index.vue          (plan 06-12) → tournaments.formats.*, tournaments.status.*, tournaments.tabs.*, tournaments.empty.*
|   - TournamentI18nKeyCoverageTest        (plan 06-13) → all of the above (Pitfall 10 mitigation)
|
| Parameter interpolation: `:from`, `:to`, `:min`, `:rounds`, `:count`, `:clan`.
| Hardcoded English strings here are authoritative; localisation happens in later
| phases (D-013 — plumbed day one, EN at launch).
*/

return [
    // Format labels (Pitfall 10: 4 formats × 4 keys = 16 leaf keys)
    'formats' => [
        'single_elimination' => [
            'label' => 'Single elimination',
            'description' => 'Knockout — one loss eliminates you.',
            'badge_class' => 'bg-amber-100 text-amber-800',
            'badge_label' => 'Single elim',
        ],
        'double_elimination' => [
            'label' => 'Double elimination',
            'description' => 'Two losses eliminate you — losers bracket gives a second chance.',
            'badge_class' => 'bg-orange-100 text-orange-800',
            'badge_label' => 'Double elim',
        ],
        'round_robin' => [
            'label' => 'Round robin',
            'description' => 'Every participant plays every other participant.',
            'badge_class' => 'bg-emerald-100 text-emerald-800',
            'badge_label' => 'Round robin',
        ],
        'swiss' => [
            'label' => 'Swiss',
            'description' => 'Score-grouped pairings over ceil(log2(N)) rounds with Buchholz tiebreaks.',
            'badge_class' => 'bg-violet-100 text-violet-800',
            'badge_label' => 'Swiss',
        ],
    ],

    // Status labels (6 states × 2 keys each = 12 leaf keys)
    'status' => [
        'draft' => ['label' => 'Draft', 'badge_class' => 'bg-zinc-100 text-zinc-700'],
        'registering' => ['label' => 'Registering', 'badge_class' => 'bg-sky-100 text-sky-800'],
        'seeded' => ['label' => 'Seeded', 'badge_class' => 'bg-indigo-100 text-indigo-800'],
        'running' => ['label' => 'Running', 'badge_class' => 'bg-emerald-100 text-emerald-800'],
        'completed' => ['label' => 'Completed', 'badge_class' => 'bg-slate-200 text-slate-700'],
        'cancelled' => ['label' => 'Cancelled', 'badge_class' => 'bg-rose-100 text-rose-800'],
    ],

    // Participant statuses (4 × 1 = 4 leaf keys)
    'participant_status' => [
        'registered' => ['label' => 'Registered'],
        'active' => ['label' => 'Active'],
        'withdrawn' => ['label' => 'Withdrawn'],
        'disqualified' => ['label' => 'Disqualified'],
    ],

    // Errors thrown by services (D-013 + Pitfall 5 swiss too few — 8 leaf keys)
    'errors' => [
        'invalid_transition' => 'Tournament status cannot transition from :from to :to.',
        'brackets_already_generated' => 'Brackets have already been generated for this tournament.',
        'swiss_too_few_participants' => 'Swiss tournaments require at least :min participants for :rounds rounds.',
        'winner_not_participant' => 'Match winner clan is not a registered tournament participant.',
        'no_self_advance' => 'A bracket cannot advance to itself.',
        'reseed_not_allowed' => 'Re-seeding is only available while no matches have been played.',
        'insufficient_participants' => 'Tournament requires at least :min participants to generate a bracket.',
        'cannot_forfeit_completed' => 'Cannot forfeit a participant after the tournament is completed.',
        'cannot_withdraw_completed' => 'Cannot withdraw a participant after the tournament is completed.',
    ],

    // Admin action labels (D-04-12-A withProperties() pattern; modal copy comes through here — 9 actions × 4 keys = 36 leaf keys)
    'actions' => [
        'open_registration' => [
            'label' => 'Open registration',
            'modal_heading' => 'Open registration?',
            'modal_description' => 'Allow clans to register for this tournament.',
            'success' => 'Registration is open.',
        ],
        'seed' => [
            'label' => 'Seed participants',
            'modal_heading' => 'Seed :count participants?',
            'modal_description' => 'Lock in seed order. Choose a strategy.',
            'success' => 'Participants seeded.',
        ],
        'reseed' => [
            'label' => 'Re-seed',
            'modal_heading' => 'Re-seed participants?',
            'modal_description' => 'Only available while no matches have been played. This will overwrite current seeds.',
            'success' => 'Participants re-seeded.',
        ],
        'start' => [
            'label' => 'Start tournament',
            'modal_heading' => 'Start the tournament?',
            'modal_description' => 'Generate brackets and materialise round-1 matches.',
            'success' => 'Tournament running.',
        ],
        'forfeit' => [
            'label' => 'Forfeit participant',
            'modal_heading' => 'Forfeit :clan?',
            'modal_description' => 'The participant will not advance to future matches. Past matches retain their results.',
            'success' => 'Participant forfeited.',
        ],
        'withdraw' => [
            'label' => 'Withdraw participant',
            'modal_heading' => 'Withdraw :clan?',
            'modal_description' => 'The participant will not advance to future matches. Past matches retain their results.',
            'success' => 'Participant withdrawn.',
        ],
        'recalculate_standings' => [
            'label' => 'Recalculate standings',
            'modal_heading' => 'Recalculate standings?',
            'modal_description' => 'Recompute wins/losses/Buchholz from current match results.',
            'success' => 'Standings recalculated.',
        ],
        'cancel' => [
            'label' => 'Cancel tournament',
            'modal_heading' => 'Cancel the tournament?',
            'modal_description' => 'This is irreversible — the tournament will be marked cancelled and removed from the public calendar.',
            'success' => 'Tournament cancelled.',
        ],
        'generate_next_swiss_round' => [
            'label' => 'Generate next Swiss round',
            'modal_heading' => 'Generate next Swiss round?',
            'modal_description' => 'Pair participants for the next round using current scores and Buchholz tiebreaks.',
            'success' => 'Next Swiss round generated.',
        ],
    ],

    // Public page tabs (5 tabs per SC-3 — 5 leaf keys)
    'tabs' => [
        'overview' => ['label' => 'Overview'],
        'bracket' => ['label' => 'Bracket'],
        'schedule' => ['label' => 'Schedule'],
        'standings' => ['label' => 'Standings'],
        'participants' => ['label' => 'Participants'],
    ],

    // Empty-state messaging (3 leaf keys)
    'empty' => [
        'participants' => 'No participants registered yet.',
        'brackets' => 'Brackets have not been generated yet.',
        'standings' => 'Standings will appear once matches are played.',
    ],

    // Stage type labels (6 × 1 = 6 leaf keys)
    'stage_types' => [
        'group' => ['label' => 'Group'],
        'elim' => ['label' => 'Elimination'],
        'winners-bracket' => ['label' => 'Winners bracket'],
        'losers-bracket' => ['label' => 'Losers bracket'],
        'grand-final' => ['label' => 'Grand final'],
        'swiss-round' => ['label' => 'Swiss round'],
    ],
];
