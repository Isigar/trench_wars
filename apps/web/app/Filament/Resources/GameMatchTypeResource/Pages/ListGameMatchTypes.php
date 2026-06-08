<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameMatchTypeResource\Pages;

use App\Filament\Base\ListRecords;
use App\Filament\Resources\GameMatchTypeResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

class ListGameMatchTypes extends ListRecords
{
    protected static string $resource = GameMatchTypeResource::class;

    /** @return array<int, Action|ActionGroup> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
