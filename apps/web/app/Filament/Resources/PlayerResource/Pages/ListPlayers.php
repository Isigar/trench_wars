<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlayerResource\Pages;

use App\Filament\Base\ListRecords;
use App\Filament\Resources\PlayerResource;

class ListPlayers extends ListRecords
{
    protected static string $resource = PlayerResource::class;
}
