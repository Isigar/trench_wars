<?php

declare(strict_types=1);

namespace App\Filament\Resources\DiscordOutboundMessageResource\Pages;

use App\Filament\Base\ViewRecord;
use App\Filament\Resources\DiscordOutboundMessageResource;
use App\Models\DiscordOutboundMessage;
use Filament\Actions;

class ViewDiscordOutboundMessage extends ViewRecord
{
    protected static string $resource = DiscordOutboundMessageResource::class;

    /** @return array<int, Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            // Mirror of the List-page row action — re-queue a failed row for
            // redelivery without leaving the View page. Shares the resource's
            // retry() so behaviour + audit logging stay identical, and gates on
            // status === 'failed' (the button disappears once re-queued).
            Actions\Action::make('retry')
                ->label(__('admin.discord_outbound_message.actions.retry'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (DiscordOutboundMessage $record): bool => $record->status === 'failed')
                ->action(function (DiscordOutboundMessage $record): void {
                    DiscordOutboundMessageResource::retry($record);
                }),
        ];
    }
}
