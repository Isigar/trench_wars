<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

/**
 * Source: .planning/phases/01-foundations/01-13-PLAN.md task 2 + Pitfall 4 mitigation.
 *
 * Filament CRUD on the spatie/laravel-permission Role model. `guard_name` is pinned
 * to `'web'` (single-guard project) — pin happens both in the Form (disabled Select
 * with default 'web') and in CreateRole::mutateFormDataBeforeCreate() so the field
 * survives even if a future migration adds extra guard options.
 *
 * No View page — audit log (plan 14) shows changes; the edit form is canonical.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('admin.role.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.role.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('admin.role.fields.name'))
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            Forms\Components\Select::make('guard_name')
                ->label(__('admin.role.fields.guard_name'))
                ->options(['web' => 'web'])
                ->default('web')
                ->disabled()
                ->dehydrated(true)
                ->required(),
            Forms\Components\Select::make('permissions')
                ->label(__('admin.role.fields.permissions'))
                ->relationship('permissions', 'name')
                ->multiple()
                ->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.role.fields.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('guard_name')
                    ->label(__('admin.role.fields.guard_name')),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label(__('admin.role.fields.permissions_count'))
                    ->counts('permissions'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
