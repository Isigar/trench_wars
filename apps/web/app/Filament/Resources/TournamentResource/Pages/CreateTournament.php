<?php

declare(strict_types=1);

namespace App\Filament\Resources\TournamentResource\Pages;

use App\Filament\Base\CreateRecord;
use App\Filament\Resources\TournamentResource;

/**
 * Source: .planning/phases/06-tournaments-brackets/06-11-PLAN.md Task 1.
 *
 * Tournament create flow is a single-step form (no wizard) — admins fill the
 * profile fields and hit Create. The lifecycle then proceeds via HeaderActions on
 * EditTournament: open_registration → seed → start → completed/cancelled.
 *
 * Pitfall 2 (RESEARCH): translatable JSONB fields (title, description) need
 * null-coercion to ['en' => ''] in mutateFormDataBeforeCreate — Filament's
 * KeyValue returns null on empty submission; HasTranslations expects an array.
 */
class CreateTournament extends CreateRecord
{
    protected static string $resource = TournamentResource::class;

    /**
     * Coerce null translatable JSONB fields to ['en' => ''] before DB write.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['title'] = $data['title'] ?: ['en' => ''];
        $data['description'] = $data['description'] ?: ['en' => ''];
        $data['status'] = $data['status'] ?? 'draft';

        return $data;
    }
}
