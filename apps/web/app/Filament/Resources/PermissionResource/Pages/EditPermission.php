<?php

declare(strict_types=1);

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Base\EditRecord;
use App\Filament\Resources\PermissionResource;

class EditPermission extends EditRecord
{
    protected static string $resource = PermissionResource::class;
}
