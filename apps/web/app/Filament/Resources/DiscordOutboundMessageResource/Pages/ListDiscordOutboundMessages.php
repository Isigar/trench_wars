<?php

declare(strict_types=1);

namespace App\Filament\Resources\DiscordOutboundMessageResource\Pages;

use App\Filament\Resources\DiscordOutboundMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListDiscordOutboundMessages extends ListRecords
{
    protected static string $resource = DiscordOutboundMessageResource::class;

    // INTENTIONALLY no getHeaderActions() — DiscordOutboundMessageResource is
    // read-only (T-05-07-01). Admin cannot create rows by hand; the observer +
    // SyncDiscordRolesJob own the outbox.
}
