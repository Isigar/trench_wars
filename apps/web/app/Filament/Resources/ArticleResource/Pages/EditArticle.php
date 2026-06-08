<?php

declare(strict_types=1);

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Base\EditRecord;
use App\Filament\Resources\ArticleResource;
use Filament\Actions;
use Filament\Actions\Action;

/**
 * Source: .planning/phases/07-cms/07-05-PLAN.md task 1.
 *
 * Delete action visibility delegates to ArticlePolicy::delete which requires
 * hasRole('super-admin') (T-07-05-05 mitigation — defence-in-depth complement
 * to PermissionSeeder's explicit omission of articles.delete from cms-editor).
 *
 * Plan 07-06 will append a publish HeaderAction calling ArticlePublishService.
 * Plan 07-07 will append a schedule HeaderAction; until those plans land the
 * status transitions happen via the form's reactive status Select.
 */
class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
