<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanApplicationResource\Pages;

use App\Filament\Resources\ClanApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListClanApplications extends ListRecords
{
    protected static string $resource = ClanApplicationResource::class;
}
