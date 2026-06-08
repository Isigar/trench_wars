<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameResource\Pages;

use App\Filament\Base\CreateRecord;
use App\Filament\Resources\GameResource;

class CreateGame extends CreateRecord
{
    protected static string $resource = GameResource::class;

    /**
     * Coerce null name to ['en' => ''] before DB write.
     *
     * Pitfall 2 (RESEARCH.md): Filament KeyValue returns null on empty
     * submission; HasTranslations expects an array, not null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['name'] = $data['name'] ?: ['en' => ''];

        return $data;
    }
}
