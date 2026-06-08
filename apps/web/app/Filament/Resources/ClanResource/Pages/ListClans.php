<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanResource\Pages;

use App\Filament\Base\ListRecords;
use App\Filament\Resources\ClanResource;

class ListClans extends ListRecords
{
    protected static string $resource = ClanResource::class;
}
