<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanTagResource\Pages;

use App\Filament\Base\EditRecord;
use App\Filament\Resources\ClanTagResource;

class EditClanTag extends EditRecord
{
    protected static string $resource = ClanTagResource::class;

    /**
     * Coerce null label to ['en' => ''] before DB write.
     *
     * Pitfall 6 (RESEARCH.md): Filament KeyValue returns null on empty
     * submission; HasTranslations expects an array, not null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['label'] = $data['label'] ?: ['en' => ''];

        return $data;
    }
}
