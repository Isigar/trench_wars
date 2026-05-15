<?php

declare(strict_types=1);

namespace App\Filament\Resources\AbuseReportResource\Pages;

use App\Filament\Resources\AbuseReportResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Source: .planning/phases/09-polish/09-11-PLAN.md task 2.
 *
 * Standard Filament v3 ViewRecord page. Permissions inherited from
 * AbuseReportResource::canView() (view-reports gate). State-machine
 * transitions live on the table row Actions (dismiss / action_with_ban),
 * NOT on this page — the View page is read-only by design (T-09-11-04 —
 * panel-level permission gate).
 */
class ViewAbuseReport extends ViewRecord
{
    protected static string $resource = AbuseReportResource::class;
}
