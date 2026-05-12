<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Source: .planning/phases/02-clans-tags/02-12-PLAN.md task 2.
 *
 * Applications RelationManager for ClanResource — read-only.
 * Applications are managed via the My Clan UI (plan 02-10); admin view is observation only.
 */
class ApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'applications';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('status')
                ->label(__('admin.clan_application.fields.status'))
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('applicant.username')
                    ->label(__('admin.clan_application.fields.user'))
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('admin.clan_application.fields.status'))
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'accepted',
                        'danger' => 'declined',
                        'secondary' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('message')
                    ->label(__('admin.clan_application.fields.message'))
                    ->limit(60)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('decided_at')
                    ->label(__('admin.clan_application.fields.decided_at'))
                    ->dateTime()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('decidedBy.username')
                    ->label('Decided by')
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }
}
