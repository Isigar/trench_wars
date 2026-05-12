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
 * Invites RelationManager for ClanResource — read-only.
 * Invites are managed via the My Clan UI (plan 02-10); admin view is observation only.
 */
class InvitesRelationManager extends RelationManager
{
    protected static string $relationship = 'invites';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('status')
                ->label(__('admin.clan_invite.fields.status'))
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invitee.username')
                    ->label(__('admin.clan_invite.fields.user'))
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('inviter.username')
                    ->label(__('admin.clan_invite.fields.invited_by'))
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('admin.clan_invite.fields.status'))
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'accepted',
                        'danger' => 'declined',
                        'secondary' => fn ($state) => in_array($state, ['revoked', 'expired']),
                    ]),

                Tables\Columns\TextColumn::make('message')
                    ->label(__('admin.clan_invite.fields.message'))
                    ->limit(60)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('decided_at')
                    ->label(__('admin.clan_invite.fields.decided_at'))
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }
}
