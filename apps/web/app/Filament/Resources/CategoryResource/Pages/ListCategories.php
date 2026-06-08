<?php

declare(strict_types=1);

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Base\ListRecords;
use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

/**
 * Source: .planning/phases/07-cms/07-05-PLAN.md task 1.
 */
class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    /** @return array<int, Action|ActionGroup> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
