<?php

declare(strict_types=1);

namespace App\Filament\Resources\TournamentResource\Pages;

use App\Filament\Base\ListRecords;
use App\Filament\Resources\TournamentResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-11-PLAN.md Task 1.
 */
class ListTournaments extends ListRecords
{
    protected static string $resource = TournamentResource::class;

    /** @return array<int, Action|ActionGroup> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
