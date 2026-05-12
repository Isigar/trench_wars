<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanResource\Pages;

use App\Filament\Resources\ClanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClan extends CreateRecord
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
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['description'] = $data['description'] ?: ['en' => ''];

        return $data;
    }
}
