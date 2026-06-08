<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanMembershipResource\Pages;

use App\Filament\Base\ListRecords;
use App\Filament\Resources\ClanMembershipResource;

class ListClanMemberships extends ListRecords
{
    protected static string $resource = ClanMembershipResource::class;
}
