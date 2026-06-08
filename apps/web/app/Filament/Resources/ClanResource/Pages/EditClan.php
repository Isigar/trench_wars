<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanResource\Pages;

use App\Filament\Base\EditRecord;
use App\Filament\Resources\ClanResource;

class EditClan extends EditRecord
{
    protected static string $resource = ClanResource::class;

    /**
     * Coerce null description to ['en' => ''] before DB write.
     *
     * Pitfall 6 (RESEARCH.md): Filament KeyValue returns null on empty
     * submission; HasTranslations expects an array, not null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['description'] = $data['description'] ?: ['en' => ''];

        return $data;
    }
}
