<?php

declare(strict_types=1);

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Base\ListRecords;
use App\Filament\Resources\PermissionResource;

class ListPermissions extends ListRecords
{
    protected static string $resource = PermissionResource::class;
}
