<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameMatchTypeResource\Pages;

use App\Filament\Resources\GameMatchTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditGameMatchType extends EditRecord
{
    protected static string $resource = GameMatchTypeResource::class;

    /**
     * Coerce null translatable JSONB fields to ['en' => ''] before DB write.
     *
     * Pitfall 2 (RESEARCH.md): Filament KeyValue returns null on empty submission;
     * HasTranslations expects an array, not null. GameMatchType has TWO translatable
     * fields (name + description) — BOTH require coercion per Pitfall 2 note.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['name'] = $data['name'] ?: ['en' => ''];
        $data['description'] = $data['description'] ?: ['en' => ''];

        return $data;
    }
}
