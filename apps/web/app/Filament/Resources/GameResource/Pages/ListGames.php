<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameResource\Pages;

use App\Filament\Base\ListRecords;
use App\Filament\Resources\GameResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

class ListGames extends ListRecords
{
    protected static string $resource = GameResource::class;

    /** @return array<int, Action|ActionGroup> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
