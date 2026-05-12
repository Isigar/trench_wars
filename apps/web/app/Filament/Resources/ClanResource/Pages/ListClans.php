<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanResource\Pages;

use App\Filament\Resources\ClanResource;
use Filament\Resources\Pages\ListRecords;

class ListClans extends ListRecords
{
    protected static string $resource = ClanResource::class;
}
