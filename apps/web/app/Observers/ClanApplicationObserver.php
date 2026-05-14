<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ClanApplication;
use App\Notifications\ClanApplicationDecided;

/**
 * Source: .planning/phases/09-polish/09-04-PLAN.md task 2.
 *
 * Fires `ClanApplicationDecided` to the applicant when a clan officer
 * accepts or declines a pending application. The state machine (plan 02-09
 * + plan 09-07 ClanApplicationService) only ever transitions a row through
 * pending → accepted | declined | cancelled. We notify on the two "decided"
 * terminal states (accepted + declined) — `cancelled` is initiated by the
 * applicant themselves so no DM/bell entry is warranted.
 *
 * NAMING NOTE — plan referenced 'approved/rejected'; the actual DB CHECK
 * constraint is 'accepted/declined' (see
 * 2026_05_12_100600_create_clan_applications_table.php). The ClanApplicationDecided
 * Notification class (plan 09-03) already encodes the 'accepted' branch as
 * the "approved" variant in its i18n_key; observer mirrors that mapping.
 *
 * Registration: via static::observe() in the ClanApplication model's
 * booted() hook (D-04-08-B precedent — GameMatch, ClanMembership, Article,
 * MatchResult, Tournament all use this idiom).
 */
class ClanApplicationObserver
{
    /**
     * State-transition hook: fires only on pending→{accepted,declined}.
     *
     * `wasChanged('status')` filters Filament inline edits to non-status
     * fields (e.g., adjusting the message). `getOriginal('status')` MUST
     * have been `pending` — re-saving an already-decided row with the same
     * status does NOT re-notify (idempotent against stray touch()).
     */
    public function updated(ClanApplication $application): void
    {
        if (! $application->wasChanged('status')) {
            return;
        }

        if (! in_array($application->status, ['accepted', 'declined'], true)) {
            return;
        }

        if ($application->getOriginal('status') !== 'pending') {
            return;
        }

        $application->loadMissing('applicant');

        $applicant = $application->applicant;
        if ($applicant === null) {
            return;
        }

        $applicant->notify(new ClanApplicationDecided($application));
    }
}
