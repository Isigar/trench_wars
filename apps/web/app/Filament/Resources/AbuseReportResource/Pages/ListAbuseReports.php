<?php

declare(strict_types=1);

namespace App\Filament\Resources\AbuseReportResource\Pages;

use App\Filament\Resources\AbuseReportResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Source: .planning/phases/09-polish/09-11-PLAN.md task 2.
 *
 * Standard Filament v3 ListRecords page. Permissions inherited from
 * AbuseReportResource::canViewAny() (view-reports gate). No header
 * actions — reports are submitted via the public POST /reports flow,
 * never created inside the panel.
 */
class ListAbuseReports extends ListRecords
{
    protected static string $resource = AbuseReportResource::class;
}
