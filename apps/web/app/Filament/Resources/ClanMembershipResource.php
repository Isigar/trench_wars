<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ClanMembershipResource\Pages;
use App\Models\Clan;
use App\Models\ClanMembership;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Source: .planning/phases/02-clans-tags/02-13-PLAN.md task 1.
 *
 * Read-only membership audit listing (D-012). Lifecycle is owned by the
 * My Clan UI flow (plans 02-09/02-10/02-11) which sets left_at on leave
 * rather than hard-deleting (D-009). Admin can observe but not mutate.
 *
 * getPages(): List + View only — NO Create, Edit, or Delete.
 */
class ClanMembershipResource extends Resource
{
    protected static ?string $model = ClanMembership::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('admin.clan_membership.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.clan_membership.plural_label');
    }

    public static function form(Form $form): Form
    {
        // Form exists for the ViewRecord page (Filament v3 requires form() on every Resource).
        // All fields are disabled + dehydrated(false) so the View page renders read-only.
        return $form->schema([
            Forms\Components\TextInput::make('user.username')
                ->label(__('admin.clan_membership.fields.user'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('clan.name')
                ->label(__('admin.clan_membership.fields.clan'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('role')
                ->label(__('admin.clan_membership.fields.role'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\DateTimePicker::make('joined_at')
                ->label(__('admin.clan_membership.fields.joined_at'))
                ->disabled()
                ->dehydrated(false),

            Forms\Components\DateTimePicker::make('left_at')
                ->label(__('admin.clan_membership.fields.left_at'))
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.clan_membership.fields.user'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('clan.name')
                    ->label(__('admin.clan_membership.fields.clan'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label(__('admin.clan_membership.fields.role'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'leader' => 'success',
                        'officer' => 'info',
                        'member' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('joined_at')
                    ->label(__('admin.clan_membership.fields.joined_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('left_at')
                    ->label(__('admin.clan_membership.fields.left_at'))
                    ->dateTime()
                    ->sortable()
                    ->color(fn ($state): string => $state === null ? 'success' : 'gray')
                    ->placeholder('Active'),
            ])
            ->filters([
                Tables\Filters\Filter::make('active_only')
                    ->label('Active memberships only')
                    ->query(fn ($query) => $query->whereNull('left_at')),

                Tables\Filters\SelectFilter::make('clan')
                    ->label(__('admin.clan_membership.fields.clan'))
                    ->relationship('clan', 'name'),

                Tables\Filters\SelectFilter::make('role')
                    ->label(__('admin.clan_membership.fields.role'))
                    ->options([
                        'leader' => 'Leader',
                        'officer' => 'Officer',
                        'member' => 'Member',
                        'recruit' => 'Recruit',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClanMemberships::route('/'),
            // No 'create' — lifecycle owned by My Clan UI (plans 02-09/02-10/02-11).
            // No 'edit' — D-009: membership rows are append-only; left_at set on leave.
            'view' => Pages\ViewClanMembership::route('/{record}'),
        ];
    }
}
