<?php

declare(strict_types=1);

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

/**
 * Source: .planning/phases/07-cms/07-05-PLAN.md task 1.
 *
 * T-07-05-07 mitigation: author_user_id is hardcoded to auth()->id() inside
 * mutateFormDataBeforeCreate so a crafted form payload cannot spoof another
 * author. The form does NOT expose an author_user_id field at all — so even
 * absent this hook, the value can only land here via mass-assignment.
 *
 * status defaults to 'draft' (matches the form default + plan policy that new
 * articles always start in draft regardless of any field shenanigans).
 *
 * Translatable JSONB fields (title.en, excerpt.en, body.en) are submitted via
 * Filament's dot-notation field paths so the trait's HasTranslations setter
 * sees the correct ['en' => '...'] shape — no mutateForm coercion needed.
 */
class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    /**
     * Force author_user_id to the currently-authenticated user (T-07-05-07).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User|null $user */
        $user = auth()->user();
        $data['author_user_id'] = $user?->id;
        $data['status'] = $data['status'] ?? 'draft';
        $data['allow_discord_announce'] = $data['allow_discord_announce'] ?? true;

        return $data;
    }
}
