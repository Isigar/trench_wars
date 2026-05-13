<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchResource\Pages;

use App\Filament\Resources\MatchResource;
use App\Models\GameMatch;
use App\Services\MatchStatusService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

/**
 * Source: .planning/phases/04-matches-manual/04-09-PLAN.md task 1.
 *
 * Pitfall 7 / T-04-09-03 (RESEARCH.md): status field is rendered for visibility but
 * `->disabledOn('edit')` on MatchResource::form() so admin cannot flip status via the
 * form. Status transitions happen via the HeaderActions below — each Action calls
 * MatchStatusService::transition($record, $to, auth user) which enforces the state
 * machine + audits the transition (T-04-09-03 mitigation).
 *
 * Pitfall 2 (RESEARCH.md): translatable JSONB fields (title, description) need
 * null-coercion to ['en' => ''] in mutateFormDataBeforeSave.
 */
class EditMatch extends EditRecord
{
    protected static string $resource = MatchResource::class;

    /**
     * Coerce null translatable JSONB fields to ['en' => ''] before DB write.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['title'] = $data['title'] ?: ['en' => ''];
        $data['description'] = $data['description'] ?: ['en' => ''];

        return $data;
    }

    /**
     * HeaderActions for status transitions — the ONLY admin path to flip status
     * (the form-level Select is ->disabledOn('edit') per Pitfall 7).
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // open → locked
            Action::make('lock_signups')
                ->label(__('admin.match.actions.lock_signups'))
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (GameMatch $record): bool => $record->status === 'open')
                ->action(function (GameMatch $record): void {
                    $causer = auth()->user();
                    if ($causer === null) {
                        return;
                    }
                    app(MatchStatusService::class)->transition($record, 'locked', $causer);
                }),

            // draft|open → cancelled (locked also allowed per state machine)
            Action::make('cancel_match')
                ->label(__('admin.match.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (GameMatch $record): bool => in_array($record->status, ['draft', 'open', 'locked'], true))
                ->action(function (GameMatch $record): void {
                    $causer = auth()->user();
                    if ($causer === null) {
                        return;
                    }
                    app(MatchStatusService::class)->transition($record, 'cancelled', $causer);
                }),

            // draft → open
            Action::make('open_signups')
                ->label(__('admin.match.actions.open_signups'))
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (GameMatch $record): bool => $record->status === 'draft')
                ->action(function (GameMatch $record): void {
                    $causer = auth()->user();
                    if ($causer === null) {
                        return;
                    }
                    app(MatchStatusService::class)->transition($record, 'open', $causer);
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
