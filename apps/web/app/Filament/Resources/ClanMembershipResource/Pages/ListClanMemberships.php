<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanMembershipResource\Pages;

use App\Filament\Resources\ClanMembershipResource;
use Filament\Resources\Pages\ListRecords;

class ListClanMemberships extends ListRecords
{
    protected static string $resource = ClanMembershipResource::class;
}
