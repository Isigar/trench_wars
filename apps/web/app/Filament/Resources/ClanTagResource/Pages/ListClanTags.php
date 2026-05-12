<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanTagResource\Pages;

use App\Filament\Resources\ClanTagResource;
use Filament\Resources\Pages\ListRecords;

class ListClanTags extends ListRecords
{
    protected static string $resource = ClanTagResource::class;
}
