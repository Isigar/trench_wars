<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchDisputeResource\Pages;

use App\Filament\Base\ViewRecord;
use App\Filament\Resources\MatchDisputeResource;

/**
 * Source: .planning/phases/09-polish/09-07-PLAN.md task 2.
 *
 * Standard Filament v3 ViewRecord page. Permissions inherited from
 * MatchDisputeResource::canView() (moderate-disputes gate). The transition
 * Action lives on the table row, not on this page — the View page is
 * read-only by design (T-09-07-06 — Information Disclosure mitigated by
 * panel-level permission gate, not page-level).
 */
class ViewMatchDispute extends ViewRecord
{
    protected static string $resource = MatchDisputeResource::class;
}
