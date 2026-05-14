<?php

declare(strict_types=1);

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

/**
 * Source: .planning/phases/07-cms/07-05-PLAN.md task 1.
 *
 * Delete action hidden when the category has any articles assigned
 * (articles.category_id FK is ON DELETE RESTRICT — plan 07-02).
 */
class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn ($record): bool => $record->articles()->count() === 0),
        ];
    }
}
