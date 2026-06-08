<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlayerResource\Pages;

use App\Filament\Base\EditRecord;
use App\Filament\Resources\PlayerResource;

class EditPlayer extends EditRecord
{
    protected static string $resource = PlayerResource::class;
}
