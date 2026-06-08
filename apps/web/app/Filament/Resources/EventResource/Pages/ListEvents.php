<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Base\ListRecords;
use App\Filament\Resources\EventResource;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    // INTENTIONALLY no getHeaderActions() — EventResource is read-only (T-04-09-06).
}
