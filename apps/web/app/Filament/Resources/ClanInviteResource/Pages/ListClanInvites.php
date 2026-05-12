<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanInviteResource\Pages;

use App\Filament\Resources\ClanInviteResource;
use Filament\Resources\Pages\ListRecords;

class ListClanInvites extends ListRecords
{
    protected static string $resource = ClanInviteResource::class;
}
