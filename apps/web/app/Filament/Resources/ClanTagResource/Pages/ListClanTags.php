<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanTagResource\Pages;

use App\Filament\Base\ListRecords;
use App\Filament\Resources\ClanTagResource;

class ListClanTags extends ListRecords
{
    protected static string $resource = ClanTagResource::class;
}
