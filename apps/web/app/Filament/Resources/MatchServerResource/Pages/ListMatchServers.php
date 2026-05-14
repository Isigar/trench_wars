<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchServerResource\Pages;

use App\Filament\Resources\MatchServerResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;

class ListMatchServers extends ListRecords
{
    protected static string $resource = MatchServerResource::class;

    /** @return array<int, Action|ActionGroup> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
