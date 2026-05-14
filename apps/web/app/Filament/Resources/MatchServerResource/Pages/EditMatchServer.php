<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchServerResource\Pages;

use App\Filament\Resources\MatchServerResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditMatchServer extends EditRecord
{
    protected static string $resource = MatchServerResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Strip nested credentials_encrypted.api_token before filling the form so
     * the API token field renders empty on edit — admin must explicitly
     * re-enter (or click reveal on the current value if `mutateFormDataBeforeFill`
     * is overridden to pre-fill). Per T-08-09-01 we do NOT pre-fill the token:
     * the password field stays empty on edit; admin re-enters to rotate, or
     * leaves blank to keep the existing value (handled by the omit-empty
     * dehydrate path on the resource form).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Show the existing token unchanged so the admin can use ->revealable()
        // to confirm what's stored. encrypted:array cast returns the array.
        if (isset($data['credentials_encrypted']) && is_array($data['credentials_encrypted'])) {
            $data['credentials_encrypted'] = [
                'api_token' => $data['credentials_encrypted']['api_token'] ?? '',
            ];
        }

        return $data;
    }

    /**
     * Preserve the existing API token when the admin leaves the password
     * field blank on edit — `dehydrateStateUsing` on the form's password
     * input returns null for empty strings, which would null-out the cast
     * column on save. This mutator drops the null key so updateOrSave
     * preserves whatever is already on the row.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! array_key_exists('credentials_encrypted', $data) || empty($data['credentials_encrypted'])) {
            unset($data['credentials_encrypted']);
        }

        return $data;
    }
}
