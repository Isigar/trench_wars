<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanApplicationResource\Pages;

use App\Filament\Base\ListRecords;
use App\Filament\Resources\ClanApplicationResource;

class ListClanApplications extends ListRecords
{
    protected static string $resource = ClanApplicationResource::class;
}
