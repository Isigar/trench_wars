<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Source: .planning/phases/09-polish/09-07-PLAN.md task 2 (Wave 5).
 *
 * Read-only Filament v3 RelationManager mounted on UserResource. Lists every
 * ban (current + historical) attached to the User record. State transitions
 * (issue/lift) happen via the UserResource bulk + per-user single Actions —
 * this manager exists for visibility + audit, not mutation. There is
 * intentionally NO CreateAction, EditAction, or DeleteAction on the table.
 *
 * Pitfall 3 mitigation: $relationship MUST EXACTLY match User::bans() HasMany
 * method name. A typo silently renders an empty tab.
 *
 * The form() method exists because Filament v3 requires it on every
 * RelationManager (used by the ViewAction modal). All fields are disabled +
 * dehydrated(false) so the modal is read-only.
 */
class BansRelationManager extends RelationManager
{
    protected static string $relationship = 'bans';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.user.relations.bans');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('ban_type')
                ->label(__('moderation.bulk.ban.ban_type'))
                ->options([
                    'temporary' => __('moderation.ban.types.temporary'),
                    'permanent' => __('moderation.ban.types.permanent'),
                ])
                ->disabled()
                ->dehydrated(false),

            Forms\Components\Textarea::make('reason')
                ->label(__('moderation.bulk.ban.reason'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\DateTimePicker::make('expires_at')
                ->label(__('moderation.bulk.ban.expires_at'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('issuedBy.username')
                ->label(__('admin.user.fields.username'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\DateTimePicker::make('lifted_at')
                ->label(__('moderation.ban.status.lifted'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\Textarea::make('lift_reason')
                ->label(__('moderation.bulk.unban.modal_description'))
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('ban_type')
                    ->label(__('moderation.bulk.ban.ban_type'))
                    ->colors([
                        'warning' => 'temporary',
                        'danger' => 'permanent',
                    ]),

                Tables\Columns\TextColumn::make('reason')
                    ->label(__('moderation.bulk.ban.reason'))
                    ->limit(60),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('moderation.bulk.ban.expires_at'))
                    ->dateTime()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('issuedBy.username')
                    ->label(__('admin.user.fields.username'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('lifted_at')
                    ->label(__('moderation.ban.status.lifted'))
                    ->dateTime()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.user.fields.last_login_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                // INTENTIONALLY no Edit/Delete actions — bans are append-only;
                // lifting happens via the UserResource unban BulkAction so
                // every mutation flows through BanService + activity_log.
            ]);
        // INTENTIONALLY no headerActions — same rationale as above.
    }

    /**
     * Read-only RelationManager — no mutations from this tab.
     */
    public function isReadOnly(): bool
    {
        return true;
    }
}
