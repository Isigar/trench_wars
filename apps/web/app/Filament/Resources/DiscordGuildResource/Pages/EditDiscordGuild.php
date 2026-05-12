<?php

declare(strict_types=1);

namespace App\Filament\Resources\DiscordGuildResource\Pages;

use App\Filament\Resources\DiscordGuildResource;
use Filament\Resources\Pages\EditRecord;

class EditDiscordGuild extends EditRecord
{
    protected static string $resource = DiscordGuildResource::class;
}
