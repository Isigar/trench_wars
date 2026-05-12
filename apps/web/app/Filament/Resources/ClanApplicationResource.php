<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ClanApplicationResource\Pages;
use App\Models\ClanApplication;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Source: .planning/phases/02-clans-tags/02-13-PLAN.md task 1.
 *
 * Read-only application audit listing (D-012). State transitions (accept, decline)
 * are performed via My Clan UI (plans 02-09/02-10). Admin observes only.
 *
 * getPages(): List + View only — NO Create, Edit, or Delete.
 */
class ClanApplicationResource extends Resource
{
    protected static ?string $model = ClanApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 7;

    public static function getModelLabel(): string
    {
        return __('admin.clan_application.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.clan_application.plural_label');
    }

    public static function form(Form $form): Form
    {
        // Form exists for the ViewRecord page (Filament v3 requires form() on every Resource).
        // All fields are disabled + dehydrated(false) so the View page renders read-only.
        return $form->schema([
            Forms\Components\TextInput::make('clan.name')
                ->label(__('admin.clan_application.fields.clan'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('applicant.username')
                ->label(__('admin.clan_application.fields.user'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('status')
                ->label(__('admin.clan_application.fields.status'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\Textarea::make('message')
                ->label(__('admin.clan_application.fields.message'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\DateTimePicker::make('decided_at')
                ->label(__('admin.clan_application.fields.decided_at'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('decidedBy.username')
                ->label('Decided by')
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clan.name')
                    ->label(__('admin.clan_application.fields.clan'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('applicant.username')
                    ->label(__('admin.clan_application.fields.user'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.clan_application.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'accepted' => 'success',
                        'declined', 'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('message')
                    ->label(__('admin.clan_application.fields.message'))
                    ->limit(60),

                Tables\Columns\TextColumn::make('decided_at')
                    ->label(__('admin.clan_application.fields.decided_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('decidedBy.username')
                    ->label('Decided by')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('admin.clan_application.fields.status'))
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'declined' => 'Declined',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('clan')
                    ->label(__('admin.clan_application.fields.clan'))
                    ->relationship('clan', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClanApplications::route('/'),
            // No 'create' — applications submitted via public clan page (plan 02-09).
            // No 'edit' — state transitions via My Clan UI only.
            'view' => Pages\ViewClanApplication::route('/{record}'),
        ];
    }
}
