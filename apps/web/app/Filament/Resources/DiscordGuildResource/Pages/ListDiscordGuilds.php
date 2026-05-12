<?php

declare(strict_types=1);

namespace App\Filament\Resources\DiscordGuildResource\Pages;

use App\Filament\Resources\DiscordGuildResource;
use Filament\Resources\Pages\ListRecords;

class ListDiscordGuilds extends ListRecords
{
    protected static string $resource = DiscordGuildResource::class;
}
