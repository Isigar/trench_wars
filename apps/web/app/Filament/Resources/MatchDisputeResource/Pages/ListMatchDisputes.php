<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchDisputeResource\Pages;

use App\Filament\Base\ListRecords;
use App\Filament\Resources\MatchDisputeResource;

/**
 * Source: .planning/phases/09-polish/09-07-PLAN.md task 2.
 *
 * Standard Filament v3 ListRecords page. Permissions inherited from
 * MatchDisputeResource::canViewAny() (moderate-disputes gate). No header
 * actions — disputes are NOT created from the admin panel.
 */
class ListMatchDisputes extends ListRecords
{
    protected static string $resource = MatchDisputeResource::class;
}
