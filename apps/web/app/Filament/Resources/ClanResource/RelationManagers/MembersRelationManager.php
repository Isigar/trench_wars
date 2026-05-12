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
 * Members RelationManager for ClanResource.
 * Allows admin to add, edit, and soft-leave memberships.
 *
 * D-009: Do NOT hard-delete membership rows — set left_at = now() instead.
 * This preserves membership history per the invariant.
 */
class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label(__('admin.clan_membership.fields.user'))
                ->relationship('user', 'username')
                ->searchable()
                ->required(),

            Forms\Components\Select::make('role')
                ->label(__('admin.clan_membership.fields.role'))
                ->options([
                    'leader' => 'Leader',
                    'officer' => 'Officer',
                    'member' => 'Member',
                    'recruit' => 'Recruit',
                ])
                ->default('recruit')
                ->required(),

            Forms\Components\DateTimePicker::make('joined_at')
                ->label(__('admin.clan_membership.fields.joined_at'))
                ->required(),

            Forms\Components\DateTimePicker::make('left_at')
                ->label(__('admin.clan_membership.fields.left_at'))
                ->nullable(),

            Forms\Components\Select::make('invited_by')
                ->label(__('admin.clan_invite.fields.invited_by'))
                ->relationship('inviter', 'username')
                ->searchable()
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.clan_membership.fields.user'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('role')
                    ->label(__('admin.clan_membership.fields.role'))
                    ->colors([
                        'warning' => 'leader',
                        'info' => 'officer',
                        'success' => 'member',
                        'secondary' => 'recruit',
                    ]),

                Tables\Columns\TextColumn::make('joined_at')
                    ->label(__('admin.clan_membership.fields.joined_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('left_at')
                    ->label(__('admin.clan_membership.fields.left_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('inviter.username')
                    ->label(__('admin.clan_invite.fields.invited_by'))
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->label('Active only')
                    ->query(fn ($query) => $query->whereNull('left_at')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // D-009: Do NOT hard-delete memberships — set left_at = now() to preserve history.
                Tables\Actions\Action::make('mark_left')
                    ->label('Mark as left')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['left_at' => now()]))
                    ->visible(fn ($record) => $record->left_at === null),
            ]);
    }
}
