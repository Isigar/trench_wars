<?php

declare(strict_types=1);

namespace App\Filament\Resources\DiscordGuildResource\Pages;

use App\Filament\Base\ListRecords;
use App\Filament\Resources\DiscordGuildResource;

class ListDiscordGuilds extends ListRecords
{
    protected static string $resource = DiscordGuildResource::class;
}
