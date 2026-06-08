<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanInviteResource\Pages;

use App\Filament\Base\ListRecords;
use App\Filament\Resources\ClanInviteResource;

class ListClanInvites extends ListRecords
{
    protected static string $resource = ClanInviteResource::class;
}
